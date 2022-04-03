<?
ini_set("display_errors","1");
ini_set("display_startup_errors","1");
ini_set('error_reporting', E_ALL);
writetolog($_REQUEST, 'new request');	
$lead = $_REQUEST['lead'];
$phone = $_REQUEST['phone'];
$email = $_REQUEST['email'];
$commentLead = $_REQUEST['commentLead'];

/* AUTH */
require_once('auth.php');

$countforlead = 0;
$kryt = 'Y';
$leadwasfound = 'N';
$dateCreateLead = date(DATE_ATOM);


$leadinfo = executeREST(
    'crm.duplicate.findbycomm',
    array(
        'TYPE' => 'PHONE',
        'VALUES' => array($phone),
        'FIELDS' => array (
            'ENTITY_TYPE' => 'LEAD',

        ),

    ),
    $domain, $auth, $user);
while($kryt == 'Y'){
$mainlead = $leadinfo['result']['LEAD'][$countforlead];


writeToLog($mainlead, 'mainleadFromPhone');

if (!empty($mainlead)) {
    $getlead = executeREST(
        'crm.lead.get',
        array(
                'ID' => $mainlead,	
                ),
    $domain, $auth, $user);
$statuslead = $getlead['result']['STATUS_SEMANTIC_ID'];
$assignlead = $getlead['result']['ASSIGNED_BY_ID'];
$dateCreate = $getlead['result']['DATE_CREATE'];

if($statuslead == 'P' and $mainlead != $lead){
    $kryt = 'N';
    $leadwasfound = 'Y';
}else{
    $countforlead = $countforlead + 1;
}
}else{
    $kryt = 'N';
}

}

if($leadwasfound == 'N') {
    $countforlead = 0;
    $kryt = 'Y';

    $leadinfo = executeREST(
        'crm.duplicate.findbycomm',
        array(
            'TYPE' => 'EMAIL',
            'VALUES' => array($email),
            'FIELDS' => array (
                'ENTITY_TYPE' => 'LEAD',
    
            ),
    
        ),
        $domain, $auth, $user);
    while($kryt == 'Y'){
    $mainlead = $leadinfo['result']['LEAD'][$countforlead];
    
    writeToLog($mainlead, 'mainleadFromEmail');
    
    if (!empty($mainlead)) {

        $getlead = executeREST(
            'crm.lead.get',
            array(
                    'ID' => $mainlead,	
                    ),
        $domain, $auth, $user);
    $statuslead = $getlead['result']['STATUS_SEMANTIC_ID'];
    $assignlead = $getlead['result']['ASSIGNED_BY_ID'];
    $dateCreate = $getlead['result']['DATE_CREATE'];

    
    if($statuslead == 'P' and $mainlead != $lead){
        $kryt = 'N';
        $leadwasfound = 'Y';
    }else{
        $countforlead = $countforlead + 1;
    }
    }else{
        $kryt = 'N';
        $merge = 'LEAD_' . $lead;

    $startworkflow = executeREST(
        'bizproc.workflow.start',
        array(
                'TEMPLATE_ID' => '461',	
                'DOCUMENT_ID' => array (
                    'crm', 'CCrmDocumentLead', $merge,
                ),
            ),
    $domain, $auth, $user);
    }
    
    }
}else{
    writeToLog('Lead was found');
    
}

$date1 = new DateTime($dateCreateLead);
$date2 = new DateTime($dateCreate);
$difference_in_seconds = $date1->format('U') - $date2->format('U');

$difference_in_seconds = $difference_in_seconds / 60 / 60;
writeToLog($difference_in_seconds);

if($leadwasfound == 'Y' and $difference_in_seconds < 1) {
        $updatelead = executeREST(
            'crm.lead.update',
            array(
					'ID' => $lead,	
					'FIELDS' => array (
						'ASSIGNED_BY_ID' => $assignlead,
                        'STATUS_ID' => 4,
						),
					'PARAMS' => array (
						'REGISTER_SONET_EVENT' => "N",
						),
                    ),
	$domain, $auth, $user);

    

    $leadurl = 'https://'. $domain . '/crm/lead/details/' . $lead . '/'; 

    $taskadd = executeREST(
        'tasks.task.add',
        array(
                	
                'fields' => array (
                    'TITLE' => 'Есть дублируюший лид',
                    'DESCRIPTION' => 'Ссылка на дублирующий лид: ' . $leadurl . 
                    ' Комментарий из дублирующего лида: ' . $commentLead,
                    'CREATED_BY' => $assignlead,
                    'RESPONSIBLE_ID' => $assignlead,
                    'UF_CRM_TASK' => array('L_' . $mainlead),
                    ),
                ),
$domain, $auth, $user);
writeToLog('taskadd');
}

if(!empty($phone)){
$conatactInfo = executeREST(
    'crm.contact.list',
    array(
        'order' => array(
            'DATE_CREATE' => 'DESC',
        ),
        'filter' => array(
            'PHONE' => $phone
        ),
        'select' => array (
            'ID',
        ),
    ),
    $domain, $auth, $user);

    $contactID = $conatactInfo['result'][0]['ID'];

    if(!empty($contactID)){
        $updatelead = executeREST(
            'crm.lead.update',
            array(
					'ID' => $lead,	
					'FIELDS' => array (
						'CONTACT_ID' => $contactID,
						),
					'PARAMS' => array (
						'REGISTER_SONET_EVENT' => "N",
						),
                    ),
	    $domain, $auth, $user);

        exit;
    }
}

if(!empty($email)){
    $conatactInfo = executeREST(
        'crm.contact.list',
        array(
            'order' => array(
                'DATE_CREATE' => 'DESC',
            ),
            'filter' => array(
                'EMAIL' => $email
            ),
            'select' => array (
                'ID',
            ),
        ),
        $domain, $auth, $user);
    
        $contactID = $conatactInfo['result'][0]['ID'];

        if(!empty($contactID)){
            $updatelead = executeREST(
                'crm.lead.update',
                array(
                        'ID' => $lead,	
                        'FIELDS' => array (
                            'CONTACT_ID' => $contactID,
                            ),
                        'PARAMS' => array (
                            'REGISTER_SONET_EVENT' => "N",
                            ),
                        ),
            $domain, $auth, $user);
    
        }
}


function executeREST ($method, array $params, $domain, $auth, $user) {
            $queryUrl = 'https://'.$domain.'/rest/'.$user.'/'.$auth.'/'.$method.'.json';
            $queryData = http_build_query($params);
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_POST => 1,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_URL => $queryUrl,
                CURLOPT_POSTFIELDS => $queryData,
            ));
            return json_decode(curl_exec($curl), true);
            curl_close($curl);
}

function writeToLog($data, $title = '') {
    $log = "\n------------------------\n";
    $log .= date("Y.m.d G:i:s") . "\n";
    $log .= (strlen($title) > 0 ? $title : 'DEBUG') . "\n";
    $log .= print_r($data, 1);
    $log .= "\n------------------------\n";
    file_put_contents(getcwd() . '/duplicate.log', $log, FILE_APPEND);
    return true;
} 