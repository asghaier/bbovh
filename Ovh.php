<?php
class Registrar_Adapter_Ovh extends Registrar_AdapterAbstract {
  public $config = array(
    'soap' => null,
    'session' => null,
    'nic' => null,
    'password' => null,
    'testMode' => null
  );
  public function __construct($options) {
    if (!extension_loaded('curl')) {
      throw new Registrar_Exception('CURL extension is not enabled');
    }
    if(isset($options['nic']) && !empty($options['nic'])) {
      $this->config['nic'] = $options['nic'];
      unset($options['nic']);
    } else {
      throw new Registrar_Exception('Domain registrar "Ovh" is not configured properly. Please update configuration parameter "Ovh API key" at "Configuration -> Domain registration".');
    }
    if(isset($options['password']) && !empty($options['password'])) {
      $this->config['password'] = $options['password'];
      unset($options['password']);
    } else {
      throw new Registrar_Exception('Domain registrar "Ovh" is not configured properly. Please update configuration parameter "Ovh API password" at "Configuration -> Domain registration".');
    }
    if(isset($options['testMode']) && !empty($options['testMode'])) {
      $this->config['testMode'] = $options['testMode'];
      unset($options['testMode']);
    } else {
      throw new Registrar_Exception('Domain registrar "Ovh" is not configured properly. Please update configuration parameter "Ovh API password" at "Configuration -> Domain registration".');
    }
  }
  public static function getConfig() {
    return array(
      'label' => 'Manages domains on Ovh via API',
      'form'  => array(
        'nic' => array(
          'text',
          array(
            'label' => 'Ovh Nic',
            'description'=>'Ovh account Nic/ID'
          )
        ),
        'password' => array(
          'password',
          array(
            'label' => 'Ovh password',
            'description'=>'Ovh manager account or SOAPI password',
            'renderPassword' => true
          )
        ),
        'testMode' => array(
          'yesno',
          array(
            'label' => 'Test Mode',
            'description'=> 'enable the TEST MODE when enabled (true), will not debit your account'
          )
        )
      )
    );
  }
  public function getTlds() {
    return array(
      '.co', '.com', '.net', '.eu',
      '.org', '.it', '.fr', '.info',
      '.tel', '.us', '.biz', '.co.uk',
      '.in', '.mobi', '.asia', '.tv',
      '.re', '.be', '.cc', '.com.fr',
      '.com.re', '.org.uk', '.me.uk', '.com.co',
      '.net.co', '.nom.co', '.co.in', '.net.in',
      '.org.in', '.firm.in', '.gen.in', '.ind.in',
    );
  }
  public function isDomainAvailable(Registrar_Domain $domain) {
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process('/Domain/Check', $params);
    return ($result['status'] == 'AVAILABLE');
  }
  public function isDomainCanBeTransfered(Registrar_Domain $domain) {
    throw new Registrar_Exception('Domain transfer checking is not implemented');
  }
  public function modifyNs(Registrar_Domain $domain) {
    $params = array(
      'domain' => $domain->getName()
    );
    $nsList = array();
    $nsList[] = $domain->getNs1();
    $nsList[] = $domain->getNs2();
    $nsList[] = $domain->getNs3();
    $nsList[] = $domain->getNs4();
    $params['ns_list'] = implode(',', $nsList);
    $result = $this->_process('/Domain/Update', $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function modifyContact(Registrar_Domain $domain) {
    $c = $domain->getContactRegistrar();
    $params = array(
      'domain' => $domain->getName()
    );
    // Set contact data
    foreach (array('Registrant', 'Admin', 'Technical', 'Billing') as $contactType)
    {
      $params[$contactType. '_Organization'] = $c->getCompany();
      $params[$contactType. '_FirstName']    = $c->getFirstName();
      $params[$contactType. '_LastName']     = $c->getLastName();
      $params[$contactType. '_Email']        = $c->getEmail();
      $params[$contactType. '_PhoneNumber']  = '+' . $c->getTelCc() . '.' . $c->getTel();
      $params[$contactType. '_Street']       = $c->getAddress1();
      $params[$contactType. '_Street2']       = $c->getAddress2();
      $params[$contactType. '_Street3']       = $c->getAddress3();
      $params[$contactType. '_City']         = $c->getCity();
      $params[$contactType. '_CountryCode']  = $c->getCountry();
      $params[$contactType. '_PostalCode']   = $c->getZip();
      $params[$contactType. '_Language']     = 'en';
    }
    $result = $this->_process('/Domain/Update', $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function transferDomain(Registrar_Domain $domain) {
    $c = $domain->getContactRegistrar();
    $params = array(
      'domain' => $domain->getName()
    );
    // Set contact data
    foreach (array('Registrant', 'Admin', 'Technical', 'Billing') as $contactType)
    {
      $params[$contactType. '_Organization'] = $c->getCompany();
      $params[$contactType. '_FirstName']    = $c->getFirstName();
      $params[$contactType. '_LastName']     = $c->getLastName();
      $params[$contactType. '_Email']        = $c->getEmail();
      $params[$contactType. '_PhoneNumber']  = '+' . $c->getTelCc() . '.' . $c->getTel();
      $params[$contactType. '_Street']       = $c->getAddress1();
      $params[$contactType. '_Street2']       = $c->getAddress2();
      $params[$contactType. '_Street3']       = $c->getAddress3();
      $params[$contactType. '_City']         = $c->getCity();
      $params[$contactType. '_CountryCode']  = $c->getCountry();
      $params[$contactType. '_PostalCode']   = $c->getZip();
      $params[$contactType. '_Language']     = 'en';
    }
    $result = $this->_process('/Domain/Transfer/Initiate', $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function getDomainDetails(Registrar_Domain $domain) {
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process('/Domain/Info', $params);
    if ($result['status'] == 'SUCCESS')
      return $this->_createDomainObj($result, $domain);
  }
  public function deleteDomain(Registrar_Domain $domain) {
    throw new Registrar_Exception('Registrar does not support domain removal.');
  }
  public function registerDomain(Registrar_Domain $domain) {
    _soap();
    _login();
    // TODO : check domain availability
    // TODO : create nic handle for owner, admin, tech and billing
    $ns1 = $domain->getNs1();
    $ns2 = $domain->getNs1();
    $ns3 = "";
    $ns4 = "";
    if($domain->getNs3())  {
      $ns3 = $domain->getNs3();
    }
    if($domain->getNs4())  {
      $ns4 = $domain->getNs4();
    }
    try {
      $this->config['soap']->resellerDomainCreate(
        $this->config['session'],
        $domain->getName(),
        "none",
        "gold",
        "whiteLabel",
        "yes",
        $this->config['nic'],
        $this->config['nic'],
        $this->config['nic'],
        $this->config['nic'],
        $ns1,
        $ns2,
        $ns3,
        $ns4,
        "",
        "",
        "",
        "",
        "",
        "",
        "",
        "",
        "",
        true
      );
    } catch(SoapFault $fault) {
      throw new Registrar_Exception('Session Termination error: \n' . $fault);
    }
    _logout();
    /*$c = $domain->getContactRegistrar();
    $params = array(
      'domain' => $domain->getName(),
      'period' => $domain->getRegistrationPeriod() . 'Y'
    );
    // Add nameservers
    $nsList = array();
    $nsList[] = $domain->getNs1();
    $nsList[] = $domain->getNs2();
    if($domain->getNs3())  {
      $nsList[] = $domain->getNs3();
    }
    if($domain->getNs4())  {
      $nsList[] = $domain->getNs4();
    }
    $params['ns_list'] = implode(',', $nsList);
    // Set contact data
    foreach (array('Registrant', 'Admin', 'Technical', 'Billing') as $contactType)
    {
      $params[$contactType . '_Organization'] = $c->getCompany();
      $params[$contactType . '_FirstName']    = $c->getFirstName();
      $params[$contactType . '_LastName']     = $c->getLastName();
      $params[$contactType . '_Email']        = $c->getEmail();
      $params[$contactType . '_PhoneNumber']  = '+' . $c->getTelCc() . '.' . $c->getTel();
      $params[$contactType . '_Street']       = $c->getAddress1();
      $params[$contactType . '_Street2']       = $c->getAddress2();
      $params[$contactType . '_Street3']       = $c->getAddress3();
      $params[$contactType . '_City']         = $c->getCity();
      $params[$contactType . '_CountryCode']  = $c->getCountry();
      $params[$contactType . '_PostalCode']   = $c->getZip();
      $params[$contactType . '_Language']     = 'en';
    }
    if ($domain->getTld() == '.asia')
    {
      $params['Registrant_DotAsiaCedLocality'] = $c->getCountry();
      $params['Registrant_DotAsiaCedEntity'] = 'naturalPerson';
      $params['Registrant_DotAsiaCedIdForm'] = 'passport';
    }
    if ($domain->getTld() == '.fr' || $domain->getTld() == '.re')
    {
      $tm = rand(100000000, 999999999);
      $params['registrant_dotFRContactEntityType'] = 'OTHER';
      $params['admin_dotFRContactEntityType'] = 'OTHER';
      $params['registrant_dotFRContactEntityName'] = $c->getName();
      $params['admin_dotFRContactEntityName'] = $c->getName();
      $params['registrant_dotFROtherContactEntity'] = $c->getName();
      $params['admin_dotFROtherContactEntity'] = $c->getName();
      $params['registrant_dotFRContactEntityTrademark'] = $tm;
      $params['admin_dotFRContactEntityTrademark'] = $tm;
    }
    if ($domain->getTld() == '.it')
    {
      $params['Registrant_dotitEntityType'] = 1;
      $params['Registrant_dotitNationality'] = $c->getCountry();
      $params['Registrant_dotitRegCode'] = $c->getDocumentNr();
      $params['Registrant_dotitHideWhois'] = ($domain->getPrivacyEnabled() ? 'YES' : 'NO');
      $params['Registrant_dotitProvince'] = $c->getState();
      for ($i = 1; $i < 5; $i++)
        $params['Registrant_dotItTerm' . $i] = 'YES';
      $params['Registrant_clientIp'] = '1.1.1.1';
      $params['Admin_dotitProvince'] = $c->getState();
      $params['Technical_dotitProvince'] = $c->getState();
    }
    if ($domain->getTld() == '.us')
    {
      $params['Registrant_usPurpose'] = 'P3';
      $params['Registrant_usNexusCategory'] = 'C11';
    }
    $result = $this->_process('/Domain/Create', $params);
    return (($result['product_0_status'] == 'PENDING')
      || ($result['product_0_status'] == 'SUCCESS'));*/
  }
  public function renewDomain(Registrar_Domain $domain) {
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process('/Domain/Renew', $params);
    return ($result['product_0_status'] == 'SUCCESS');
  }
  public function enablePrivacyProtection(Registrar_Domain $domain) {
    $cmd = '/Domain/PrivateWhois/Enable';
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process($cmd, $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function disablePrivacyProtection(Registrar_Domain $domain) {
    $cmd = '/Domain/PrivateWhois/Disable';
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process($cmd, $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function getEpp(Registrar_Domain $domain) {
    $d = $this->getDomainDetails($domain);
    return $d->getEpp();
  }
  public function lock(Registrar_Domain $domain) {
    $cmd = '/Domain/RegistrarLock/Enable';
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process($cmd, $params);
    return ($result['status'] == 'SUCCESS');
  }
  public function unlock(Registrar_Domain $domain) {
    $cmd = '/Domain/RegistrarLock/Disable';
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process($cmd, $params);
    return ($result['status'] == 'SUCCESS');
  }
  /**
   * Runs an api command and returns parsed data.
   * @param string $command
   * @return array
   */
  private function _process($command, $params) {
    // Set authentication params
    $params['nic'] = $this->config['nic'];
    $params['password'] = $this->config['password'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->_getApiUrl() . $command);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, "Internet.bs ClientExec plugin V2.5");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $data = curl_exec($ch);
    if ($data === false) {
      $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
      $this->getLog()->err($e);
      curl_close($ch);
      throw $e;
    }
    curl_close($ch);
    if ($data)
      return $this->_parseResult($data);
  }
  /**
   * Parses data returned by request.
   * @param string $data
   * @return array
   */
  private function _parseResult($data) {
    $lines = explode("\n", $data);
    $result = array();
    foreach ($lines as $line)
    {
      list($varName, $value) = explode("=", $line);
      $result[strtolower(trim($varName))] = trim($value);
    }
    if ((array_key_exists('status', $result))
      && ($result['status'] == 'FAILURE'))
    {
      throw new Registrar_Exception($result['message']);
    }
    if ($this->isTestEnv()) error_log(print_r($result, 1));
    return $result;
  }
  public function isTestEnv() {
    return $this->_testMode;
  }
  /**
   * Api URL.
   * @return string
   */
  private function _getApiUrl() {
    if ($this->isTestEnv())
      return 'https://testapi.internet.bs';
    return 'https://api.internet.bs';
  }
  /**
   * Creates domain object from received data array.
   * @param Registrar_Domain $domain
   * @return Registrar_Domain
   */
  private function _createDomainObj($result, Registrar_Domain $domain) {
    $type = 'contacts_registrant_';
    $tel = explode('.', $result[$type . 'phonenumber']);
    // domain specific
    if (array_key_exists($type . 'firstname', $result))
      $name = $result[$type . 'firstname'];
    if (array_key_exists($type . 'lastname', $result))
      $name .= ' ' . $result[$type . 'lastname'];
    if (!array_key_exists($type . 'organization', $result))
      $result[$type . 'organization'] = '';
    if ($domain->getTld() == 'fr')
      $name = $result[$type . 'dotfrcontactentityname'];
    if ($domain->getTld() == 'it')
      $result['transferauthinfo'] = '';
    $c = new Registrar_Domain_Contact();
    $c->setName($name)
      ->setEmail($result[$type . 'email'])
      ->setCompany($result[$type . 'organization'])
      ->setTel($tel[1])
      ->setTelCc($tel[0])
      ->setAddress1($result[$type . 'street'])
      ->setAddress2($result[$type . 'street2'])
      ->setAddress3($result[$type . 'street3'])
      ->setCity($result[$type . 'city'])
      ->setCountry($result[$type . 'country'])
      ->setZip($result[$type . 'postalcode']);
    if(isset($result['nameserver_0'])) {
      $domain->setNs1($result['nameserver_0']);
    }
    if(isset($result['nameserver_1'])) {
      $domain->setNs2($result['nameserver_1']);
    }
    if(isset($result['nameserver_2'])) {
      $domain->setNs3($result['nameserver_2']);
    }
    if(isset($result['nameserver_3'])) {
      $domain->setNs4($result['nameserver_3']);
    }
    $privacy = 0;
    if (array_key_exists('privatewhois', $result))
    {
      $privacy =  ($result['privatewhois'] == 'FULL')
        || ($result['privatewhois'] == 'PARTIAL');
    }
    $domain->setExpirationTime(strtotime($result['expirationdate']));
    $domain->setPrivacyEnabled($privacy);
    $domain->setEpp($result['transferauthinfo']);
    $domain->setContactRegistrar($c);
    return $domain;
  }
  /**
   * Checks whether privacy is enabled.
   * @param Registrar_Domain $domain
   * @return bool
   */
  private function _isPrivacyEnabled(Registrar_Domain $domain) {
    $params = array(
      'domain' => $domain->getName()
    );
    $result = $this->_process('/Domain/PrivateWhois/Status', $params);
    return (($result['status'] == 'SUCCESS')
      && (($result['privatewhoisstatus'] == 'FULL')
      || ($result['privatewhoisstatus'] == 'PARTIAL')));
  }
  private function _soap() {
    if (!$this->config['soap']) {
      try {
        $this->config['soap'] = new SoapClient("https://www.ovh.com/soapi/soapi-re-1.63.wsdl");
      } catch(SoapFault $fault) {
        throw new Registrar_Exception('SOAPI Initialization error: \n' . $fault);
      }
    }
  }
  private function _login() {
    _soap();
    if (!$this->config['session']) {
      try {
        $this->config['session'] = $this->config['soap']->login($this->config['nic'],$this->config['password'],"en", false);
      } catch(SoapFault $fault) {
        throw new Registrar_Exception('Session Initialization error: \n' . $fault);
      }
    }
  }
  private function _logout($session) {
    if ($this->config['session']) {
      try {
        $this->config['soap']->logout($session);
      } catch(SoapFault $fault) {
        throw new Registrar_Exception('Session Termination error: \n' . $fault);
      }
      $this->config['session'] = null;
    }
  }
}
