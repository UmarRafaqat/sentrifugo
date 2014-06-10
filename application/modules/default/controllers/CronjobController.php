<?php
/********************************************************************************* 
 *  This file is part of Sentrifugo.
 *  Copyright (C) 2014 Sapplica
 *   
 *  Sentrifugo is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  Sentrifugo is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with Sentrifugo.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  Sentrifugo Support <support@sentrifugo.com>
 ********************************************************************************/

class Default_CronjobController extends Zend_Controller_Action
{

    private $options;
    public function preDispatch()
    {		 		
    }
	
    public function init()
    {
        $this->_options= $this->getInvokeArg('bootstrap')->getOptions();		
    }

    public function indexAction()
    {
	$this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        
        $date = new Zend_Date();
   
        $email_model = new Default_Model_EmailLogs();
        $cron_model = new Default_Model_Cronstatus();
        
        $cron_status = $cron_model->getActiveCron('General');
        if($cron_status == 'yes')
        {
            try
            {
                //updating cron status table to in-progress
                $cron_data = array(
                    'cron_status' => 1,
                    'cron_type' => 'General',
                    'started_at' => gmdate("Y-m-d H:i:s"),
                );

                $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, '');

                if($cron_id != '')
                {
                    $mail_data = $email_model->getNotSentMails();
                    if(count($mail_data) > 0)
                    {
                        foreach($mail_data as $mdata)
                        {
                            $options = array();
                            $options['header'] = $mdata['header'];
                            $options['message'] = $mdata['message'];
                            $options['subject'] = $mdata['emailsubject'];
                            $options['toEmail'] = $mdata['toEmail'];
                            $options['toName'] = $mdata['toName'];
                            if($mdata['cc'] != '')
                                $options['cc'] = $mdata['cc'];
                            if($mdata['bcc'] != '')
                                $options['bcc'] = $mdata['bcc'];
                            // to send email
                            
                            $mail_status = sapp_Mail::_email($options);
                            
                            $mail_where = array('id=?' => $mdata['id']);
                            $new_maildata['modifieddate'] = gmdate("Y-m-d H:i:s");
                            $new_maildata['is_sent'] = 1;
                            if($mail_status != 'error')
                            {                              
                                //to udpate email log table that mail is sent.
                                $id = $email_model->SaveorUpdateEmailData($new_maildata,$mail_where);                                 
                            }                                               
                        }//end of for loop
                        
                    }//end of mails count if
                    //updating cron status table to completed.                    
                    $cron_data = array(
                        'cron_status' => 0,                      
                        //'completed_at' => $date->get('yyyy-MM-dd HH:mm:ss'),
                        'completed_at' => gmdate("Y-m-d H:i:s"),
                    );
                    $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, "id = ".$cron_id);
                }//end of cron status id if  
            }
            catch(Exception $e)
            {
                //echo $e->getMessage();
            }
        }//end of cron status if
    }//end of index action
    
    /**
     * This action is used to send mails to employees for passport expiry,and credit card expiry
     */
    public function empexpiryAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        
        $email_model = new Default_Model_EmailLogs();
        $cron_model = new Default_Model_Cronstatus();
        
        $cron_status = $cron_model->getActiveCron('Employee expiry');
        
        $earr = array('i94' => 'I94','visa' => 'Visa' ,'passport' => 'Passport');
        if($cron_status == 'yes')
        {
            try
            {
                //updating cron status table to in-progress
                $cron_data = array(
                    'cron_status' => 1,
                    'cron_type' => 'Employee expiry',
                    'started_at' => gmdate("Y-m-d H:i:s"),
                );

                $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, '');

                if($cron_id != '')
                {
                    $calc_date = new DateTime(date('Y-m-d'));
                    $calc_date->add(new DateInterval('P1M'));
                    $print_date = $calc_date->format(DATEFORMAT_PHP);
                    $calc_date = $calc_date->format('Y-m-d');
                    $mail_data = $email_model->getEmpExpiryData($calc_date);
                    if(count($mail_data) > 0)
                    {
                        foreach($mail_data as $mdata)
                        {
                            $base_url = 'http://'.$this->getRequest()->getHttpHost() . $this->getRequest()->getBaseUrl();
                            $view = $this->getHelper('ViewRenderer')->view;
                            $this->view->emp_name = $mdata['userfullname'];                           
                            $this->view->etype = $earr[$mdata['etype']];                                                                                                                
                            $this->view->expiry_date = $print_date;
                            $text = $view->render('mailtemplates/empexpirycron.phtml');
                            $options['subject'] = APPLICATION_NAME.': '.$earr[$mdata['etype']].' renewal';
                            $options['header'] = 'Greetings from '.APPLICATION_NAME;
                            $options['toEmail'] = $mdata['emailaddress'];  
                            $options['toName'] = $mdata['userfullname'];
                            $options['message'] = $text;
                            $options['cron'] = 'yes';
                            
                            sapp_Global::_sendEmail($options);
                            //sapp_Mail::_email($options);
                        }
                    }
                    $cron_data = array(
                            'cron_status' => 0,
                            'completed_at' => gmdate("Y-m-d H:i:s"),
                        );
                    $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, "id = ".$cron_id);
                }//end of cron status id if  
            }
            catch(Exception $e)
            {
                //echo $e->getMessage();
            }
        }//end of cron status if
    }//end of emp expiry action
    /**
     * This action is to remind managers to approve leaves of his team members before end of month.
     */
    public function leaveapproveAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        
        $email_model = new Default_Model_EmailLogs();
        $cron_model = new Default_Model_Cronstatus();
        
        $cron_status = $cron_model->getActiveCron('Approve leave');
                
        if($cron_status == 'yes')
        {
            try
            {
                //updating cron status table to in-progress
                $cron_data = array(
                    'cron_status' => 1,
                    'cron_type' => 'Approve leave',
                    'started_at' => gmdate("Y-m-d H:i:s"),
                );

                $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, '');

                if($cron_id != '')
                {
                    $from_date = date('Y-m-01');
                    $to_date = date('Y-m-d');
                    $mail_data = $email_model->getLeaveApproveData($from_date,$to_date);
                    if(count($mail_data) > 0)
                    {
                        foreach($mail_data as $mdata)
                        {
                            $base_url = 'http://'.$this->getRequest()->getHttpHost() . $this->getRequest()->getBaseUrl();
                            $view = $this->getHelper('ViewRenderer')->view;
                            $this->view->emp_name = $mdata['mng_name'];
                            $this->view->team = $mdata['team'];
                            $text = $view->render('mailtemplates/leaveapprovecron.phtml');
                            $options['subject'] = APPLICATION_NAME.': Leave(s) pending for approval';
                            $options['header'] = 'Pending Leaves';
                            $options['toEmail'] = $mdata['mng_email'];
                            $options['toName'] = $mdata['mng_name'];
                            $options['message'] = $text;
                            $options['cron'] = 'yes';
                           
                            sapp_Global::_sendEmail($options);
                        }
                    }
                    $cron_data = array(
                            'cron_status' => 0,
                            'completed_at' => gmdate("Y-m-d H:i:s"),
                        );
                    $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, "id = ".$cron_id);
                }//end of cron status id if  
            }
            catch(Exception $e)
            {
                //echo $e->getMessage();
            }
        }//end of cron status if
    }//end of leave approve action
    /**
     * This action is to send email to HR group when due date of requisition is completed.
     */
    public function requisitionAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        
        $email_model = new Default_Model_EmailLogs();
        $cron_model = new Default_Model_Cronstatus();
        
        $cron_status = $cron_model->getActiveCron('Requisition expiry');
                
        if($cron_status == 'yes')
        {
            try
            {
                //updating cron status table to in-progress
                $cron_data = array(
                    'cron_status' => 1,
                    'cron_type' => 'Requisition expiry',
                    'started_at' => gmdate("Y-m-d H:i:s"),
                );

                $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, '');

                if($cron_id != '')
                {
                    $calc_date = new DateTime(date('Y-m-d'));
                    $calc_date->add(new DateInterval('P15D'));
                    $print_date = $calc_date->format(DATEFORMAT_PHP);
                    $calc_date = $calc_date->format('Y-m-d');
                    $mail_data = $email_model->getRequisitionData($calc_date);
                    if(count($mail_data) > 0)
                    {
                        //echo "<pre>";print_r($mail_data);echo "</pre>";
                        foreach($mail_data as $did => $mdata)
                        {
                            if(defined("REQ_HR_".$did))
                            {
                                $base_url = 'http://'.$this->getRequest()->getHttpHost() . $this->getRequest()->getBaseUrl();
                                $view = $this->getHelper('ViewRenderer')->view;
                                $this->view->emp_name = "HR";
                                $this->view->print_date = $print_date;
                                $this->view->req = $mdata['req'];
                                $this->view->base_url = $base_url;
                                $text = $view->render('mailtemplates/requisitioncron.phtml');
                                $options['subject'] = APPLICATION_NAME.': Renew requisition expiry';
                                $options['header'] = 'Requisition Expiry';
                                $options['toEmail'] = constant("REQ_HR_".$did);
                                $options['toName'] = "HR";
                                $options['message'] = $text;
                                $options['cron'] = 'yes';

                                sapp_Global::_sendEmail($options);
                                //sapp_Mail::_email($options);
                            }
                        }
                    }
                    $cron_data = array(
                            'cron_status' => 0,
                            'completed_at' => gmdate("Y-m-d H:i:s"),
                        );
                    $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, "id = ".$cron_id);
                }//end of cron status id if  
            }
            catch(Exception $e)
            {
                //echo $e->getMessage();
            }
        }//end of cron status if
    }//end of requisition action.
    
    /**
     * This action is used to send notification to inactive users.
     */
    public function inactiveusersAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout()->disableLayout();
        
        $email_model = new Default_Model_EmailLogs();
        $cron_model = new Default_Model_Cronstatus();
        
        $cron_status = $cron_model->getActiveCron('Inactive users');
                
        if($cron_status == 'yes')
        {
            try
            {
                //updating cron status table to in-progress
                $cron_data = array(
                    'cron_status' => 1,
                    'cron_type' => 'Inactive users',
                    'started_at' => gmdate("Y-m-d H:i:s"),
                );

                $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, '');

                if($cron_id != '')
                {
                    $calc_date = new DateTime(date('Y-m-d'));
                    $calc_date->sub(new DateInterval('P3M'));
                    $print_date = $calc_date->format(DATEFORMAT_PHP);
                    $calc_date = $calc_date->format('Y-m-d');
                    $mail_data = $email_model->getInactiveusersData($calc_date);
                    if(count($mail_data) > 0)
                    {
                        //echo "<pre>";print_r($mail_data);echo "</pre>";
                        foreach($mail_data as $did => $mdata)
                        {                            
                            $base_url = 'http://'.$this->getRequest()->getHttpHost() . $this->getRequest()->getBaseUrl();
                            $view = $this->getHelper('ViewRenderer')->view;
                            $this->view->emp_name = $mdata['userfullname'];
                            $this->view->print_date = $print_date;
                            $this->view->user_id = $mdata['employeeId'];
                            $this->view->base_url = $base_url;
                            $text = $view->render('mailtemplates/inactiveusercron.phtml');
                            $options['subject'] = APPLICATION_NAME.': Sentrifugo account inactivated';
                            $options['header'] = 'Employee inactivated';
                            $options['toEmail'] = $mdata['emailaddress'];
                            $options['toName'] = $mdata['userfullname'];
                            $options['message'] = $text;
                            $options['cron'] = 'yes';

                            sapp_Global::_sendEmail($options);
                            //sapp_Mail::_email($options);
                            
                        }
                    }
                    $cron_data = array(
                            'cron_status' => 0,
                            'completed_at' => gmdate("Y-m-d H:i:s"),
                        );
                    $cron_id = $cron_model->SaveorUpdateCronStatusData($cron_data, "id = ".$cron_id);
                }//end of cron status id if  
            }
            catch(Exception $e)
            {
                //echo $e->getMessage();
            }
        }//end of cron status if
    }//end of inactiveusers action.
    

    /**
     * This action is used to save update json in logmanager(removes 30 days before content  and saves in logmanagercron) .
     */
      
    public function logcronAction(){
	
	 $this->_helper->viewRenderer->setNoRender(true);
     $this->_helper->layout()->disableLayout();
        
	   $logmanager_model = new Default_Model_Logmanager();
	   $logmanagercron_model = new Default_Model_Logmanagercron();
	   $logData = $logmanager_model->getLogManagerData();
	   $i = 0;
	   if(count($logData) > 0){
	    // echo '<pre>'; print_r($logData); exit;
	     foreach($logData as $record){
		     if(isset($record['log_details']) && !empty($record['log_details'])){
		     
		        $id = $record['id'];
		        $menuId = $record['menuId'];
		        $actionflag = $record['user_action'];
		        $userid = $record['last_modifiedby'];
		        $keyflag = $record['key_flag'];
		        $date = $record['last_modifieddate'];
	
				$jsondetails = '{"testjson":['.$record['log_details'].']}';
				$jsonarr = @get_object_vars(json_decode($jsondetails));

				$mainTableJson = '';
				$cronTableJson = '';
				if(!empty($jsonarr))
				{
				  //echo '<pre>'; print_r($jsonarr); exit;
				  $mainJsonArrayCount = count($jsonarr['testjson']);
				  
				  foreach($jsonarr['testjson'] as $key => $json){
				   $jsonVal = @get_object_vars($json);
				   if(!empty($jsonVal)){
				  
					    $jsondate = explode(' ',$jsonVal['date']);
					    $datetime1 = new DateTime($jsondate[0]);
						$datetime2 = new DateTime();				 
		                $interval = $datetime1->diff($datetime2);
		                $interval = $interval->format('%a');
		                if($interval > 30){
		                
		                  if($cronTableJson == ''){
		                   	 $cronTableJson .=  json_encode($jsonVal);
		                   }else{
		                     $cronTableJson .=  ','.json_encode($jsonVal);
		                   }
		                 if(isset($jsonVal['recordid']) && $jsonVal['recordid'] != ''){
				            $keyflag = $jsonVal['recordid'];
				         }		                 		                               
		                }else{
		                   if($mainTableJson == ''){
		                   	 $mainTableJson .=  json_encode($jsonVal);
		                   }else{
		                     $mainTableJson .=  ','.json_encode($jsonVal);
		                   } 		                  
		                }
				   }
				   if(($mainJsonArrayCount-1) == $key){ // if all are greater than 30 days 
				     if($mainTableJson == ''){
		            	$mainTableJson .=  json_encode($jsonVal);
		            }
				   }
				 }  //echo '<hr>'.$mainTableJson.'--**--'.$mainJsonArrayCount.'----'.$cronTableJson;
				 try{ 
				 
					 if($cronTableJson != '' && $mainTableJson != ''){
					    $result = $logmanager_model->UpdateLogManagerWhileCron($id,$mainTableJson);
					     if($result){
			               $InsertId = $logmanagercron_model->InsertLogManagerCron($menuId,$actionflag,$cronTableJson,$userid,$keyflag,$date);
			             }
			             $i++;
					 }
		           	            
	              }catch(Exception $e){
	                 echo $e->getMessage(); exit;
	              }
				}
		     }	     
	     
	     }//echo '<hr>'.'No of rows affected--'.$i;    
	    
	   }
	}
	
}
