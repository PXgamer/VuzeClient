<?php

namespace Cosmologist\VuzeClient;

/**
 * Class VuzeClient
 * @package Cosmologist\VuzeClient
 *
 * A class for controlling the Vuze torrent client.
 * Require installed XML over Http plugin for Vuze.
 *
 * This class is based on a class of application Azureus PHP Control Layer.
 * The code has been rewritten, optimized and prepared for use in third-party projects.
 *
 * @example https://github.com/Cosmologist/vuze-client/blob/master/README.md
 */
class VuzeClient
{
    protected $host = '';
    protected $port = 0;
    protected $username = '';
    protected $password = '';

    protected $connectionId = '';
    protected $pluginId = '';
    protected $downloadManagerId = '';
    protected $torrentManagerId = '';
    protected $requestID = 0;

    /**
     * Constructor
     *
     * @param string $host hostname or IP of Vuze
     * @param int $port port of Vuze
     * @param string $username username for access to Vuze (optionally)
     * @param string $password password for access to Vuze (optionally)
     */
    public function __construct($host = '127.0.0.1', $port = 6884, $username = '', $password = '')
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;

        $this->init();
    }

    /**
     * Initial operations
     */
    protected function init()
    {
        $arr = $this->call("getSingleton");
        $this->connectionId = trim($arr['_connection_id']);
        $this->pluginId = trim($arr['_object_id']);
    }

    /**
     * Start or restart torrent downloading
     *
     * @param string|int $id torrent ID
     */
    public function start($id)
    {
        $this->call("restart", $id);
    }

    /**
     * Force start torrent
     *
     * @param string|int $id torrent ID
     */
    public function forceStart($id)
    {
        $this->call("setForceStart", $id, "true");
    }

    /**
     * Stop torrent downloading
     *
     * @param string|int $id torrent ID
     */
    public function stop($id)
    {
        $this->call("stop", $id);
    }

    /**
     * Move up torrent
     *
     * @param string|int $id torrent ID
     */
    public function moveUp($id)
    {
        $this->call("moveUp", $id);
    }

    /**
     * Move down torrent
     *
     * @param string|int $id torrent ID
     */
    public function moveDown($id)
    {
        $this->call("moveDown", $id);
    }

    /**
     * Remove torrent
     *
     * @param string|int $id torrent ID
     */
    public function remove($id)
    {
        $this->call("remove", $id);
    }

    /**
     * Add torrent from string representation of torrent file
     *
     * @param string $data string representation of torrent file
     * @return array information about torrent
     */
    public function addTorrent($data)
    {
        // Create torrent
        $torrent = $this->call("createFromBEncodedData[byte[]]", $this->getTorrentManagerId(), bin2hex($data));

        // Add download
        return $this->call("addDownload[Torrent]", $this->getDownloadManagerId(), $torrent['_object_id']);
    }

    /**
     * Add torrent from URL
     *
     * @param string $url url of torrent
     * @return array information about torrent
     */
    public function addTorrentUrl($url)
    {
        return $this->call("addDownload[URL]", $this->getDownloadManagerId(), $url);
    }

    /**
     * Get list of downloads
     *
     * @return array
     */
    public function getDownloads()
    {
        return $this->call("getDownloads", $this->getDownloadManagerId());
    }

    /**
     * Get download manager ID
     *
     * @return string torrent manager ID
     */
    protected function getDownloadManagerId()
    {
        if (strlen($this->downloadManagerId) == 0) {
            $arr = $this->call("getDownloadManager", $this->pluginId);
            $this->downloadManagerId = $arr['_object_id'];
        }

        return $this->downloadManagerId;
    }

    /**
     * Return torrent manager ID
     *
     * @return string torrent manager ID
     */
    protected function getTorrentManagerId()
    {
        if (strlen($this->torrentManagerId) == 0) {
            $arr = $this->call("getTorrentManager", $this->pluginId);
            $this->torrentManagerId = $arr['_object_id'];
        }

        return $this->torrentManagerId;
    }

    /**
     * Generate unique request ID
     *
     * @return integer request ID
     */
    protected function getRequestId()
    {
        return ++$this->requestID;
    }

    /**
     * Call the web service
     *
     * Method generate request to server, send it, get response and return it parsed
     *
     * @param string $method name of method
     * @param string $objectId ID of Vuze internal entities
     * @param array $parameter sending parameter
     * @return array parsed response from service
     */
    protected function call($method, $objectId = "", $parameter = null)
    {
        $xml = "";

        // Adding object_id and connection_id to request
        if (strlen($objectId) > 0 && strlen($this->connectionId) > 0) {
            $xml = "<OBJECT><_object_id>" . $objectId . "</_object_id></OBJECT>";
            $xml .= "<CONNECTION_ID>" . $this->connectionId . "</CONNECTION_ID>";
        }

        // Adding method name to request
        $xml = "<METHOD>" . $method . "</METHOD>" . $xml . "<REQUEST_ID>" . $this->getRequestId() . "</REQUEST_ID>";

        // Adding parameter to request
        if (!is_null($parameter)) {
            $xml .= "<PARAMS><ENTRY>";

            // if parameter is numeric - then it is a object ID
            if (is_numeric($parameter)) {
                $xml .= "<OBJECT><_object_id>" . $parameter . "</_object_id></OBJECT>";
            } else {
                $xml .= $parameter;
            }

            $xml .= "</ENTRY></PARAMS>";
        }

        $xml = "<REQUEST>" . $xml . "</REQUEST>";

        // Send request and return parsed response
        return $this->deepObjectToArray(simplexml_load_string($this->sendRequest($xml)));
    }

    /**
     * Send request to Xml over Http plugin, parse response and return it
     *
     * @param string $xml XML request to Xml over Http plugin
     * @throws Exception exceptions trought when method can't connect to server, or when response format is unexpected
     * @return string response from plugin in XML
     */
    protected function sendRequest($xml)
    {
        // if authentication required
        if (strlen($this->username) && strlen($this->password)) {
            $auth = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password) . "\r\n";
        } else {
            $auth = '';
        }

        // Generate the request to server
        $request =
            "POST /process.cgi HTTP/1.1\r\n" .
            "Host: " . $this->host . ":" . $this->port . "\r\n" .
            $auth .
            "Content-Length: " . strlen($xml) . "\r\n\r\n" .
            $xml;

        // Create a TCP/IP socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new Exception("socket_create() failed: reason: " . socket_strerror(socket_last_error()));
        }

        // Attempting to connect
        $result = socket_connect($socket, $this->host, $this->port);
        if ($result === false) {
            throw new Exception("socket_connect() failed: reason: ($result) " . socket_strerror(socket_last_error($socket)));
        }

        // Write to socket
        socket_write($socket, $request, strlen($request));

        // Reading response
        $response = "";
        while ($chunk = socket_read($socket, 2048)) {
            $response .= $chunk;
        }

        // Closing socket
        socket_close($socket);

        // Parse XML from response
        $parts = explode("<?xml", $response);

        if (sizeof($parts) != 2) {
            throw new Exception("Unexpected response format");
        }

        $xml = '<?xml' . $parts[1];

        return $xml;
    }


    /**
     * Convert object to array recursively
     *
     * @param object|array $obj
     * @return array mixed
     */
    function deepObjectToArray($obj)
    {
        $arr = array();
        $arrObj = is_object($obj) ? get_object_vars($obj) : $obj;
        foreach ($arrObj as $key => $val) {
            $val = (is_array($val) || is_object($val)) ? $this->deepObjectToArray($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }
}