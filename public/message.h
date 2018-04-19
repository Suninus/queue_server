/*
 * message.h
 * Desc : message definition
 * Author: lxyfirst@163.com
 */

#pragma once

#include "template_packet.h"
#include "system.pb.h"



// message definition: msgid + head + body
// msgid : module (1 byte) + type (1 byte)

//system message
typedef TemplatePacket<REGISTER_REQUEST,SSHead,RegisterRequest> SSRegisterRequest;
typedef TemplatePacket<REGISTER_RESPONSE,SSHead,RegisterResponse> SSRegisterResponse;

typedef TemplatePacket<STATUS_REQUEST,SSHead,StatusRequest> SSStatusRequest;
typedef TemplatePacket<STATUS_RESPONSE,SSHead,StatusResponse> SSStatusResponse;


typedef TemplatePacket<VOTE_REQUEST,SSHead,VoteData> SSVoteRequest ;
typedef TemplatePacket<VOTE_RESPONSE,SSHead,VoteResponse> SSVoteResponse ;

typedef TemplatePacket<VOTE_NOTIFY,SSHead,VoteData> SSVoteNotify;

typedef TemplatePacket<SYNC_QUEUE_REQUEST,SSHead,SyncQueueRequest> SSSyncQueueRequest ;
typedef TemplatePacket<SYNC_QUEUE_RESPONSE,SSHead,SyncQueueData> SSSyncQueueResponse ;

typedef TemplatePacket<FORWARD_REQUEST,SSHead,ForwardData> SSForwardRequest ;
typedef TemplatePacket<FORWARD_RESPONSE,SSHead,ForwardData> SSForwardResponse ;

