/*
 * client_udp_handler.cpp
 * Author: lixingyi
 */



#include "client_udp_handler.h"
#include "worker_util.h"
#include "queue_processor.h"

ClientUdpHandler::ClientUdpHandler()
{
    // TODO Auto-generated constructor stub

}

ClientUdpHandler::~ClientUdpHandler()
{
    // TODO Auto-generated destructor stub
}



int ClientUdpHandler::process_packet(const udp_packet* p)
{
    if(p->data[0] != '{') return 0 ;

    Document request ;
    if(json_decode(p->data,p->data + p->data_size,request)!=0) return 0 ;

    char remote_host[16] = {0} ;
    framework::addr2str(remote_host,sizeof(remote_host),&p->addr) ;

    int action = json_get_value(request,FIELD_ACTION,0) ;
    if(action <= 0 ) return -1 ;

    debug_log_format(get_logger(),"recv host:%s action:%d size:%d",remote_host,action,p->data_size) ;

    if((!is_leader() ) && action < ACTION_LOCAL_START)
    {
        if( is_forward_request() )
        {
            SourceData source ;
            source.is_tcp = 0 ;
            source.addr = p->addr ;
            if(get_worker().forward_to_leader(source,p->data,p->data_size)==0 )
            {
                return 0 ;
            }
        }

        //cannot forward , return leader info
        request.RemoveAllMembers() ;
        request.AddMember(FIELD_ACTION,ACTION_GET_LEADER,request.GetAllocator() );
    }

    if( QueueProcessor::process(request) ==0)
    {
        rapidjson::StringBuffer buffer ;
        json_encode(request,buffer) ;
        this->send(&p->addr,buffer.GetString(),buffer.GetSize() ) ;
    }

    return 0 ;


}





