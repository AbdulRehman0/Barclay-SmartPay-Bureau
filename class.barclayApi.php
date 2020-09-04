<?php
interface BarclayInterface{
    public function chargePaymentSingle();
    public function chargePaymentMonthly($storageIndicator);
    public function cancelPayment();
    public function updatePayment($data=array());
    public function getPayment();

}
class BarclayApi implements BarclayInterface{
    private $ApiUrl;

    private $environment;
    
    private $enable3ds;

    private $clientID;
    private $enterpriseID;
    
    private $version;
    private $authToken;
    private $currencyISO;

    private $stringToHash;

    private $transactionTime;

    private $totalAmount;
    //var for data
    private $requester;
    private $card;
    private $billingAddress;
    private $authType;
    private $arg0;
    //neccessary for getpayment
    private $transRef;
// step1 calling construct to set enterpriseID
// step2 calling setEnvironment to set clientID, currency and environment
// step3 calling setAuthtype;
    public function __construct($eID,$ver='20'){
        $this->enterpriseID = $eID;
        $this->stringToHash = $this->enterpriseID;
        $this->version = $ver;
        $this->enable3ds=false;
    }
    
    public static function getInstance(){
        return new self();
    }

//set environmet and clientId
    public function setEnvironment($env,$merchantPrefix,$currency,$url){
        $this->ApiUrl=$url;
        
        $this->currencyISO=$currency;
        if($env=="E"){
            $this->clientID=$merchantPrefix.'E'.$currency;
            $this->environment="ECommerce";    
        }
        else{
            $this->clientID=$merchantPrefix.'M'.$currency;
            $this->environment="MOTO";
        }
        $stringToHash .=$this->clientID;

    }
    public function setAmount($amount){
        $this->totalAmount=$amount*100;
    }
    public function setTransRef($transRef){
        $this->transRef=$transRef;
    }
    public function setAuthToken($shared_key,$transNo=''){
        $this->setTransactionTime();
        $this->stringToHash = $this->enterpriseID . $this->clientID . $transNo . $this->transRef  . $this->transactionTime . $this->card['PAN'] . $this->card['expiry'] . $this->totalAmount . $this->billingAddress['line1'] . $this->billingAddress['postcode']. $this->authType ;
        $this->authToken = hash_hmac("sha256", $this->stringToHash, $shared_key);
        $this->setRequester($transNo);
    }

    private function setRequester($transNo){
        if(!isset($this->authToken))
            return false;
        $this->requester=array(
            "authToken" => $this->authToken, 
            "enterpriseID" => $this->enterpriseID, 
            "clientID" => $this->clientID,
            "environment" => $this->environment,
            "version"=>$this->version
        );
        if($transNo!='') $this->requester['transNo'] = $transNo;
        return true;
    }

    
    public function setAuthType($authType){
        $this->authType=$authType;
    }
    public function setCard($cardNo,$exp,$csc){
        $this->card=array(
            "PAN"=>$cardNo,
            "expiry"=>$exp,
            "cvData"=>array("CV2"=>$csc)
        );
    }
    public function setBillingAddress($line1,$postCode,$firstName="",$lastName="",$country="",$line2=""){
        $this->billingAddress=array(
            "firstName"=>$firstName,
            "lastName"=>$lastName,
            "line1"=>$line1,
            "line2"=>$line2,
            "country"=>$country,
            "postcode"=>$postCode
        );
    }

    public function set3dsEnable($en3ds){
        $this->enable3ds = $en3ds;
    }

    public function setArg0($storepage){
        $this->arg0 =
        array(
            "arg0"=>array(
                "requester"=>$this->requester,
                "billingAddress"=>$this->billingAddress,
                "transactionTime"=> $this->transactionTime,
                "card"=> $this->card,
                "purchaseAmount"=>$this->totalAmount,
                "currencyCode"=>$this->currencyISO,
                "storeResultPage"=>$storepage,
                "authType"=>$this->authType,
                "paymentMethod"=>"Card",
                "authenticate"=> $this->enable3ds,
                "validate"=>true
            )
        );
    }
    //add optional parameters in data eg custom fields webbasketitems etc
    public function appendArg0($data,$key,$inArray=""){
        if(!isset($this->requester))
            return false;
        if($inArray!=""){
            if(array_key_exists($inArray,$this->arg0['arg0'])){
                $this->arg0['arg0']["$inArray"]["$key"]=$data;
                return true;
            }else return false;
        }
        $this->arg0['arg0']["$key"]=$data;
        return true;
    }
    

    public function setTransactionTime(){
        $this->transactionTime=date("Y-m-d")."T".date("H:i:s").'.000';
    }
    private function newSoapClient(){
        return new SoapClient(
            $this->ApiUrl ,
            array( 
                "style" => SOAP_DOCUMENT,
                "encoding" => SOAP_LITERAL,
                "cache_wsdl" => WSDL_CACHE_BOTH, 
                "trace" => 1,
                "exceptions" => 0 )
            );
    }

    
    public function chargePaymentSingle(){
        $client=$this->newSoapClient();

        try{
            $result=$client->beginWebPayment($this->arg0);
        }
        catch (SoapFault $ex) { 
            print("");
             $result=$ex;
             print("");
            
            }
        return $result;
    }
    public function chargePaymentMonthly($storageIndicator){
        $client=$this->newSoapClient();

        $this->appendArg0($storageIndicator,"storageIndicator","card");
        if($storageIndicator=="first") $initialPayment=true;else $initialPayment=false;
        $recurringDetails=array("cardholderAgreement"=>"recurring",'initialPayment'=>$initialPayment);
        $this->appendArg0($recurringDetails,"recurringPayment");
        $authenticationRequest=array("threeRIIndicator"=>"RECURRING");
        $this->appendArg0($authenticationRequest,"authenticationRequest");

        try{
            $result=$client->beginWebPayment($this->arg0);
        }
        catch (SoapFault $ex) { 
            print("");
             $result=$ex;
             print("");
            
            }
        return $result;
    }
    public function cancelPayment(){
        $client=$this->newSoapClient();
        $this->arg0=array(
            "arg0"=>array(
                "requester"=>$this->requester,
                "transactionTime"=>$this->transactionTime,
                "transactionReference"=>$this->transRef
            )
        );
        try{
            $result=$client->cancelWebPayment($this->arg0);
        }
        catch (SoapFault $ex) { 
            print("");
             $result=$ex;
             print("");
            }

        return $result;
    }
    public function updatePayment($data=array()){
        // return $this->client->updateWebPayment($data);
    }
    public function getPayment(){
        $client=$this->newSoapClient();
        $arg0=array(
            "arg0"=>array(
                "requester"=>$this->requester,
                "transactionTime"=>$this->transactionTime,
                "transactionReference"=>$this->transRef
            )
        );
        try{
            $result=$client->getWebPayment($arg0);
        }
        catch (SoapFault $ex) { 
            print("");
             $result=$ex;
             print("");
            }

        return $result;
    }

}
/*  getpayment's steps
    1)call constructor to set enterpriseID
    2)call setEnvironment to set clientID
    3)call setTransactionRef
    4)call setAuthToken
    5)call getPayment
*/
/*
    Cancel Payment's steps
    1)call constructor to set enterpriseID
    2)call setEnvironment to set  clientID
    3)call setTransactionRef
    4)call setAuthToken
    5)call cancelPayment
*/ 
/*
    charge payment
    1)call constructor to set enterpriseID
    2)call setEnvironment to set  clientID
    3)call setAuthType values "AuthAndSettle","AuthOnly","Settle",etc...
    4)call setCard
    5)call setBillingAddress
    6)call setAmount without multiplying 100
    7)call setAuthToken with shared_key
    8)call setArg0 with redirect url if the 3ds is enabled barclay redirect the users to this url
    9)call chargePaymentSingle Or chargePaymentMonthly
    optional fields 
    11)call set3dsEnable to enable 3ds environment default is false
    10)call appendArg0(value,key) eg.for customfield appendArg0(array("name"=>test,"value"=>2321),"customField")
*/
?>