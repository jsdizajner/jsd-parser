<?php
defined('ABSPATH') || exit;


class JSD__PARSER_XML extends JSD__PARSER_FACTORY
{

    public $test_data = JSD_PARSER_FRAMEWORK_DIR . 'xml/test.xml';
    private $xml_name;
    private $xml_api;
    private $xml_local;
    private $is_test = false;

    public function __construct($settings)
    {
        if (!is_array($settings)) : return false; endif;
        $this->xml_name = $settings['_xml_name'];
        $this->xml_api = $settings['_api_path'];
        $this->xml_local = JSD_PARSER_FRAMEWORK_DIR . 'xml/' . $this->xml_name . '.xml';
        $this->is_test = $settings['test_data'];

        if (!file_exists($this->xml_local) or $settings['auto_update'] === true) :
            $this->download_xml_from_api($this->xml_local, $this->xml_api);
        endif;
    }

    /**
     * Import Data from SVORTO API XML
     * @return void
     */
    public function download_xml_from_api($local, $api)
    {
        if (filter_var($api, FILTER_VALIDATE_URL) === FALSE) : return 'INVALID XML API'; endif;
        return file_put_contents($local, file_get_contents($api));
    }

    /**
     * Send all the data from Local XML File
     * @return object $xml DATA
     */
    public function get_xml_data()
    {
        if ($this->is_test === true ) {
            $xml = simplexml_load_file($this->test_data, 'SimpleXMLElement', LIBXML_NOCDATA);
            return $xml;
        }
        $xml = JSD_PARSER_FRAMEWORK_DIR . '/xml/' . $this->xml_name . '.xml';
        $xml = simplexml_load_file($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $xml;
    }
}
