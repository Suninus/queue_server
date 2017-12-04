<?php

class QueueClient
{
    private $tcp_flag = false ;
    private $timeout = null ;
    private $max_data_size = 10240 ;
    private $host_list = null ;
    private $queue_name = '' ;
    private $master = null ;

    function __construct( $host_list,$queue_name='',$tcp_flag=false,$timeout=2,$max_data_size=10240)
    {
        $this->host_list = $host_list ;
        $this->queue_name = $queue_name ;
        $this->tcp_flag =  $tcp_flag ;
        $this->timeout =  $timeout ;
        $this->max_data_size = $max_data_size ;

    }

      
    /*
     * @brief  send request and recv response 
     * @array
     */
    private function do_request($request)
    {
        $seq = rand() ;
        $request['seq']=$seq ;
        $send_data = json_encode($request) ;
        $sock = null ;
        foreach($this->host_list as $host)
        {
            if(!empty($this->master)) $host = $this->master ;

            if($this->tcp_flag)  $protocol = 'tcp://'  ;
            else $protocol =  'udp://' ;
            if(!empty($sock) ) fclose($sock) ;
            $sock = fsockopen($protocol . $host['host'],$host['port'],$errno,$errstr,$this->timeout);
            if(!$sock) continue ;
            stream_set_timeout($sock,$this->timeout) ;
            stream_set_chunk_size($sock,$this->max_data_size) ;

            $ret = fwrite($sock, $send_data, strlen($send_data));
            if($ret != strlen($send_data) )  continue ;
            $buf = fread($sock,$this->max_data_size);
            if(!$buf) continue ;
            $result = json_decode($buf,true) ;
            if ( !is_array($result) ) continue ;
            if ( $result['seq'] == $seq )
            {
                unset($result['seq']) ;
                return $result ;
            }
            else if( isset($result['leader_host']) )
            {
                $this->master = ['host'=>$result['leader_host'],'port'=>$result['leader_port'] ] ;
            }
            
            
        }

        return null ;

    }

    /*
     * @brief  set current queue name
     * 
     */
    function set_queue_name($queue_name)
    {
        $this->queue_name = $queue_name ;
    }


    /*
     * @brief push message into queue
     * @return array , array['code'] , array['msg_id'] 
     */
    function produce($data,$delay,$retry,$ttl)
    {
        if(is_array($data) ) $data = json_encode($data) ;
        $request = array( 
            "action"=>1 , 
            "queue"=>$this->queue_name , 
            "data" => $data ,
            "delay"=> $delay, 
            "ttl"=> $ttl,
            "retry"=> $retry,
        );

        return $this->do_request($request) ;
    }

    /*
     * @brief pop message from queue
     * @return array , array['code'] , array['msg_id']  , array['data']
     */
    function consume()
    {
        $now = time() ;
        $request = array( "action"=>2 , "queue"=>$this->queue_name , );
        return $this->do_request($request) ;
        
    }

    /*
     * @brief confirm message 
     * @return array , array['code'] 
     */
    function confirm($msg_id)
    {
        $now = time() ;
        $request = array( "action"=>3 , "queue"=>$this->queue_name ,"msg_id"=>$msg_id,);
        return $this->do_request($request) ;
    }

    /*
     * @brief monitor queue
     * @return array , array['code']  ,array['size'] , array['max_size']
     */
    function monitor()
    {
        $now = time() ;
        $request = array( "action"=>104 , "queue"=>$this->queue_name );
        return $this->do_request($request) ;
    }

    function list_queue()
    {
        $now = time() ;
        $request = array( "action"=>7);
        return $this->do_request($request) ;
    }

    function get_leader()
    {
        $now = time() ;
        $request = array( "action"=>8);
        return $this->do_request($request) ;
    }



}

function consume_confirm($client)
{
    $result = $client->consume() ;
    if(is_array($result) && isset($result["msg_id"]) ) 
    {
        $client->confirm($result["msg_id"]) ;
    }

    return $result;
}


function bench($host_list,$count)
{
    $min_time = 1000000.0 ;
    $max_time = 0.0000001;
    $fail = 0 ;
    $total_time = 0.0000001;

    $client = new QueueClient($host_list,"test_queue") ;

    for($i=0 ; $i < $count ; ++$i)
    {
        $begin_time = microtime() ;
        $now = time() ;
        $result = $client->produce(array("order_id"=>$i),$now,60,$now+3600) ;
        //$result = consume_confirm($client) ;
        //var_dump($result) ;
        if(empty($result) || empty($result["msg_id"]) )
        {
            ++$fail ;
        }
       
        $consume_time = microtime() - $begin_time ;
        if($consume_time >0.0000001)
        {
            if($min_time > $consume_time) $min_time = $consume_time ;
            if($max_time < $consume_time) $max_time = $consume_time ;
            $total_time += $consume_time ;
        }
    }

    printf("total:%d fail:%d min:%f max:%f avg:%f\n",$count,$fail,$min_time,$max_time,$total_time/$count) ;
}

function bench_process($process,$count,$host_list)
{

    for($i = 0 ; $i < $process ; ++$i)
    {

        $pid = pcntl_fork() ;
        if($pid ==0)
        {
            sleep(1) ;
            bench($host_list,$count) ;
            exit(0) ;
        }
    }

    exit(0) ;
}


$host_list = array(
    array('host'=>'127.0.0.1','port'=>1111), 
    array('host'=>'127.0.0.1','port'=>1112) 
) ;

//bench_process(4,10000,$host_list) ;
//exit ;
//bench($host_list,250) ;

$begin_time = microtime() ;
$station_id = "test" ;
$queue_name = "task#${station_id}:order:event" ;
//$queue_name = uniqid('test_') ;

$client = new QueueClient($host_list,$queue_name,true,2,20480) ;

$msg_data = 'test_' . time() ;
$result = $client->produce($msg_data,time(),0,time()) ;
assert($result['code'] == 0 && $result['msg_id'] >0) ;

$result  = $client->consume();
assert($result['code'] == 0 && $result['msg_id'] >0 && strlen($result['data']) >1 ) ;

$msg_id = $result['msg_id'] ;
$result  = $client->confirm($msg_id);
assert($result['code'] == 0) ;

$msg_data = 'test_' . time() ;
$result = $client->produce($msg_data,time(),2,time() + 10 ) ;
assert($result['code'] == 0 && $result['msg_id'] >0) ;

$result  = $client->consume();
assert($result['code'] == 0 && $result['msg_id'] >0 && $result['data'] == $msg_data ) ;

sleep(1) ;
$result  = $client->consume();
assert($result['code'] == 0 && empty($result['msg_id']) && empty($result['data']) ) ;

sleep(2) ;
$result  = $client->consume();
assert($result['code'] == 0 && $result['msg_id'] >0 && $result['data'] == $msg_data ) ;

$msg_id = $result['msg_id'] ;
$result  = $client->confirm($msg_id);
assert($result['code'] == 0) ;

sleep(3) ;
$result  = $client->consume();
assert($result['code'] == 0 && empty($result['msg_id']) && empty($result['data']) ) ;

var_dump($client->list_queue()) ;
var_dump($client->get_leader()) ;

?>
