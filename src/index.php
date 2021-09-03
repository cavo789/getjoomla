<?php
/**
 * GetJoomla.
 *
 * Script to download a Joomla package from the Github repository maintained by
 * the French AFUJ association (https://www.joomla.fr)
 *
 * php version 7.4.
 *
 * @package   GetJoomla
 *
 * @author    Best Project <kontakt@bestproject.pl>
 * @copyright 2016-2021 (c)
 * @license   MIT <https://github.com/cavo789/getjoomla/blob/main/LICENSE>
 *
 * @see https://github.com/cavo789/getjoomla/
 *
 * Also worked on this script:
 *   * Yann Gomiero <https://github.com/YGomiero>
 *   * Christophe Avonture <https://github.com/cavo789>
 */

// phpcs:disable PSR1.Files.SideEffects

namespace GetJoomla;

/**
 * GetJoomla installer class.
 */
class Installer
{
    /**
     * URL to the repository with all Joomla ZIP packages.
     *
     * @var string
     */
    const URL_VERSIONS = [
        'https://api.github.com/repos/AFUJ/joomla-cms-fr/releases',
        'https://api.github.com/repos/Opware2000/Joomla-4-French-Full-release/releases',
    ];

    // The default key from URL_VERSIONS
    private $defaultURLKey=0;

    /**
     * Base URL of the current site.
     *
     * @var string
     */
    private $baseUrl = '';

    /**
     * Error message (if there is) returned by Github like a API rate
     * limit count exceeded.
     *
     * @var string
     */
    private $gitErrorMessage = '';

    /**
     * Latest Joomla version, the most recent one. Retrieve from Github.
     *
     * @var string f.i. "Joomla! 3.9.22 Stable version francisée v1"
     */
    private $joomlaLatestVersion = '';

    /**
     * List of all Joomla versions retrieved on Github.
     *
     * @var array<mixed>
     */
    private $joomlaAllVersions = [];

    /**
     * CURL connection handle.
     *
     * @var resource
     */
    private $connection;

    /**
     * Github data cache.
     *
     * @var array<mixed>
     */
    private $cache = [
        'versions' => [],
        'latest'   => '',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Enable Error reporting
        ini_set('display_errors', '-1');
        error_reporting(E_ALL);

        // Disable execution time limit
        set_time_limit(0);

        // Get script base URL
        $this->setBaseUrl('//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    }

    /**
     * Get list of available Joomla! Releases.
     *
     * @return array<string, string>
     */
    public function getVersions(): array
    {
        if (empty($this->cache['versions'])) {
            try {
                $buffer = $this->getURLContents(self::URL_VERSIONS[$this->defaultURLKey]);

                $list = json_decode($buffer);

                $url = '';

                // Search for full installation asset
                foreach ($list as $version) {
                    foreach ($version->assets as $asset) {
                        if (stripos($asset->name, 'Full_Package.zip')) {
                            $url = $asset->browser_download_url;
                        }
                    }

                    $this->cache['versions'][$version->name] = $url;
                }
            } catch (\Exception $exception) {
                // Github has probably returned an error; capture it
                $this->cache['versions'][$exception->getMessage()] = '';
            }
        }

        return $this->cache['versions'];
    }

    /**
     * Get informations about latest Joomla! release.
     *
     * @return string
     */
    public function getLatestVersion(): string
    {
        if ('' === $this->cache['latest']) {
            $buffer = $this->getURLContents(self::URL_VERSIONS[$this->defaultURLKey] . '/latest');

            $tmp = json_decode($buffer, true);

            if (isset($tmp['message'])) {
                $this->gitErrorMessage = $tmp['message'];

                return '';
            }

            $this->cache['latest'] = $tmp['name'] ?? '';
        }

        return $this->cache['latest'];
    }

    /**
     * Download package, unpack it, install and redirect to
     * Joomla! installation page.
     *
     * @param string $urlZip URL to Joomla! installation package.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function prepare(string $urlZip): void
    {
        $path = __DIR__ . '/joomla.zip';

        // Download zip only if not yet there
        if (!file_exists($path)) {
            $this->downloadFile($urlZip, $path);
        }

        if (!file_exists($path)) {
            throw new \RuntimeException(
                sprintf(
                    "The file %s wasn't downloaded successfully",
                    $path
                ),
                502
            );
        }

        // Cache files are no more needed
        $this->removeCache();

        // **************************
        // *** Remove this script ***
        // **************************
        unlink(__FILE__);

        $package = new \ZipArchive();

        if (true === $package->open($path)) {
            $package->extractTo(__DIR__);
            $package->close();

            if (!unlink($path)) {
                throw new \RuntimeException(
                    sprintf(
                        'Impossible to remove the file %s',
                        $path
                    ),
                    502
                );
            }
        } else {
            throw new \RuntimeException(
                sprintf(
                    'Cannot extract %s',
                    $path
                ),
                502
            );
        }

        // Redirect to installation; don't use php header() function
        // to avoid the warning "Cannot modify header information - headers already sent by ..."
        // https://forum.joomla.fr/forum/développeurs/développements/2021933-problème-avec-getjoomla-fr?p=2023045#post2023045
        // header('Location: installation/index.php');
        die('<script>location.replace("installation/index.php"); </script>');
    }

    /**
     * Get base URL of the current site.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set base URL of the current site.
     *
     * @param string $baseUrl Base URL of the current site
     *
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Which URLs from URL_VERSIONS should be used?
     *
     * @param integer $key Define the entry to use
     *
     * @return void
     */
    public function setDefaultUrl(int $key): void
    {
        $this->defaultURLKey = $key;
    }

    /**
     * Check Class requirements.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function checkRequirements(): void
    {
        // Check if PHP can get remote contect
        if (!ini_get('allow_url_fopen') or !function_exists('curl_init')) {
            throw new \RuntimeException(
                'This script require <b>CURL</b> or <b>allow_url_fopen</b> have
                to be enabled in PHP configuration.',
                502
            );
        }

        // Check if server allow to extract zip files
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException(
                'Class <b>ZipArchive</b> is not available in your current PHP configuration.',
                502
            );
        }

        if (!is_writable(__DIR__)) {
            throw new \RuntimeException(
                'The folder ' . __DIR__ . " is not writable, please check folder's permissions.",
                502
            );
        }
    }

    /**
     * Generate a list of URLs from where to download Joomla
     *
     * @return string
     */
    public function getURLs(): string
    {
        $options = '';

        foreach (self::URL_VERSIONS as $key => $url) {
            $options .= sprintf(
                '<option value="%s">%s</option>',
                $key,
                $url
            );
        }

        return $options;
    }

    /**
     * Return a html `<option>...</option>` tag with the all Joomla versions,
     * except the latest one.
     *
     * @return string
     */
    public function getVersionsOptions(): string
    {
        $options = '';

        //region Get the all versions cache
        $cache = $this->loadCacheFile('versions');

        if ('' === $cache) {
            // Try to retrieve the latest Joomla version from Github.
            $this->joomlaAllVersions = $this->getVersions();
            file_put_contents(__DIR__ . '/getjoomla.'.$this->defaultURLKey.'.versions.cache', json_encode($this->joomlaAllVersions));
        // file_put_contents(sys_get_temp_dir() . '/getjoomla.versions.cache', json_encode($this->joomlaAllVersions));
        } else {
            $this->joomlaAllVersions = json_decode($cache, true);
        }
        //endregion

        foreach ($this->joomlaAllVersions as $version => $url) {
            if ($version !== $this->joomlaLatestVersion) {
                $options .= sprintf(
                    '<option value="%s">%s</option>',
                    $url,
                    $version
                );
            }
        }

        return $options;
    }

    /**
     * Return a html `<option>...</option>` tag with the latest Joomla version.
     *
     * @return string
     */
    public function getLatestsVersionOptions(): string
    {
        //region Get the latest version cache
        $cache = $this->loadCacheFile('latest');

        if ('' === $cache) {
            // Try to retrieve the latest Joomla version from Github.
            $this->joomlaLatestVersion = $this->getLatestVersion();
            file_put_contents(__DIR__ . '/getjoomla.'.$this->defaultURLKey.'.latest.cache', json_encode($this->joomlaLatestVersion));
        } else {
            $this->joomlaLatestVersion = json_decode($cache);
        }
        //endregion

        $url = $this->joomlaAllVersions[$this->joomlaLatestVersion];

        return sprintf(
            '<option value="%s" style="font-weight:700">%s</option>',
            $url,
            $this->joomlaLatestVersion
        );
    }

    /**
     * Initialize variables.
     *
     * @return void
     */
    public function initialize(): void
    {
        // Try to retrieve the latest Joomla version from Github.
        $this->joomlaLatestVersion = $this->getLatestVersion();

        if ('' === $this->joomlaLatestVersion) {
            throw new \RuntimeException(
                sprintf(
                    'The URL %s has returned an empty string. Strange...',
                    self::URL_VERSIONS[$this->defaultURLKey]
                )
            );
        }

        // The error message is initialized by the getLatestVersion() function
        // when there was something wrong with Github
        if ('' !== $this->gitErrorMessage) {
            throw new \RuntimeException(
                sprintf(
                    'Github has refused the connection and returned ' .
                    'the following error message:<br/><strong>%s</strong>',
                    $this->gitErrorMessage
                ),
                502
            );
        }
    }

    /**
     * Remove the cached files.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    private function removeCache(): void
    {
        $arr = ['latest', 'versions'];

        foreach ($arr as $file) {
            $path = __DIR__ . '/getjoomla.' . $file . '.cache';

            if (file_exists($path)) {
                if (false === unlink($path)) {
                    throw new \RuntimeException(
                        sprintf(
                            'The file %s was impossible to remove',
                            $path
                        )
                    );
                }
            }
        }
    }

    /**
     * Load the data from the cache file only if the cache exists and is
     * not obsolete.
     *
     * @param string $name File name ("latest" or "versions")
     *
     * @return string Content of the file or empty string if obsolete
     */
    private function loadCacheFile(string $name): string
    {
        $path = __DIR__ . '/getjoomla.' . $name . '.cache';

        if (file_exists($path) && (filemtime($path) > (time() - (60 * 15)))) {
            return (string) file_get_contents($path);
        }

        return '';
    }

    /**
     * Get contents of a file via URL (http).
     *
     * @param string $url URL of a file.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    private function getURLContents($url): string
    {
        $content = '';

        if (\function_exists('curl_init')) {
            $this->prepareConnection($url, null);
            $content = curl_exec($this->connection);
        } else {
            $options = [
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                ],
            ];

            $content = file_get_contents(
                $url,
                false,
                stream_context_create($options)
            );
        }

        if (!is_string($content)) {
            throw new \RuntimeException(
                sprintf(
                    '%s has failed to open %s',
                    function_exists('curl_init') ? 'curl' : 'file_get_contents',
                    $url
                ),
                502
            );
        }

        return $content;
    }

    /**
     * Prepare CURL connection.
     *
     * @param string    $url    URL to be used in connection.
     * @param \resource $handle File handle to be used in connection.
     *
     * @return void
     */
    private function prepareConnection(string $url, $handle): void
    {
        if (!is_resource($this->connection)) {
            $this->connection = curl_init();

            curl_setopt($this->connection, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->connection, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->connection, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }

        // Set the URL to visit
        if ('' !== $url) {
            curl_setopt($this->connection, CURLOPT_URL, $url);
        }

        // Set file handle
        if (is_resource($handle)) {
            curl_setopt($this->connection, CURLOPT_TIMEOUT, 100);
            curl_setopt($this->connection, CURLOPT_FILE, $handle);
            curl_setopt($this->connection, CURLOPT_FOLLOWLOCATION, true);
        }
    }

    /**
     * Download a file to local filesystem.
     *
     * @param string $url  URL to the zip we need to download
     * @param string $path Path where to store the downloaded file
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    private function downloadFile(string $url, string $path): void
    {
        if (function_exists('curl_init')) {
            // Create file handle
            $handle = fopen($path, 'w+');

            if (!is_resource($handle)) {
                throw new \RuntimeException(
                    sprintf(
                        'Opening file %s has returned an error',
                        $path
                    ),
                    502
                );
            }

            // Prepare CURL connection
            $this->prepareConnection($url, $handle);

            // Run CURL, download the file
            curl_exec($this->connection);

            $error = curl_error($this->connection);

            if (!empty($error)) {
                throw new \RuntimeException('(Curl) ' . $error, 502);
            }

            // Close file handle
            fclose($handle);

            // Close CURL connection
            curl_close($this->connection);
        } else {
            $options = [
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                ],
            ];

            $data = file_get_contents(
                $url,
                false,
                stream_context_create($options)
            );

            if (false === $data) {
                throw new \RuntimeException(
                    sprintf(
                        'Impossible to get the content of %s',
                        $url
                    )
                );
            }

            if (!file_put_contents($path, $data)) {
                throw new \RuntimeException(
                    sprintf(
                        'Impossible to create the %s file',
                        $path
                    )
                );
            }
        }
    }
}

xdebug_info();

echo 10/0;
echo "<h1>INSIDE ".__FILE__.", line ".__LINE__."</h1>";
die();

// Entry point
$action=filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

if ('' !== $action) {
    $key=filter_input(INPUT_GET, 'key', FILTER_SANITIZE_NUMBER_INT) ?? 0;

    try {
        $installer = new Installer();
        $installer->setDefaultUrl($key);
        switch ($action) {
            case 'getVersionsOptions':
                die($installer->getVersionsOptions());

            case 'getLatestsVersionsOptions':
                die($installer->getLatestsVersionOptions());

            default:
                break;
        }
    } catch (\Exception $exception) {
        die('<div class="error">An error has occured: ' . $exception->getMessage() . '</div>');
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
        <meta http-equiv="Pragma" content="no-cache" />
        <meta http-equiv="Expires" content="0" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <meta name="author" content="">
        <title>GetJoomla</title>

        <!-- Bootstrap core CSS -->
        <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">

        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
        <script type="text/javascript" src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>

        <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
          <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
        <![endif]-->

        <style>
            .error {
                background-color: #fce4e4;
                border: 1px solid #fcc2c3;
                padding: 20px 30px;
            }

            .container {
                padding-top: 30px;
                padding-bottom: 30px;
            }

            .windows8 {
                position: relative;
                width: 56px;
                height: 56px;
                margin: auto;
                top: 45%
            }

            .windows8 .wBall {
                position: absolute;
                width: 53px;
                height: 53px;
                opacity: 0;
                transform: rotate(225deg);
                -o-transform: rotate(225deg);
                -ms-transform: rotate(225deg);
                -webkit-transform: rotate(225deg);
                -moz-transform: rotate(225deg);
                animation: orbit 5.4425s infinite;
                -o-animation: orbit 5.4425s infinite;
                -ms-animation: orbit 5.4425s infinite;
                -webkit-animation: orbit 5.4425s infinite;
                -moz-animation: orbit 5.4425s infinite
            }

            .windows8 .wBall .wInnerBall {
                position: absolute;
                width: 7px;
                height: 7px;
                background: #fff;
                left: 0;
                top: 0;
                border-radius: 7px
            }

            .windows8 #wBall_1 {
                animation-delay: 1.186s;
                -o-animation-delay: 1.186s;
                -ms-animation-delay: 1.186s;
                -webkit-animation-delay: 1.186s;
                -moz-animation-delay: 1.186s
            }

            .windows8 #wBall_2 {
                animation-delay: .233s;
                -o-animation-delay: .233s;
                -ms-animation-delay: .233s;
                -webkit-animation-delay: .233s;
                -moz-animation-delay: .233s
            }

            .windows8 #wBall_3 {
                animation-delay: .4765s;
                -o-animation-delay: .4765s;
                -ms-animation-delay: .4765s;
                -webkit-animation-delay: .4765s;
                -moz-animation-delay: .4765s
            }

            .windows8 #wBall_4 {
                animation-delay: .7095s;
                -o-animation-delay: .7095s;
                -ms-animation-delay: .7095s;
                -webkit-animation-delay: .7095s;
                -moz-animation-delay: .7095s
            }

            .windows8 #wBall_5 {
                animation-delay: .953s;
                -o-animation-delay: .953s;
                -ms-animation-delay: .953s;
                -webkit-animation-delay: .953s;
                -moz-animation-delay: .953s
            }

            @keyframes orbit {
                0% {
                    opacity: 1;
                    z-index: 99;
                    transform: rotate(180deg);
                    animation-timing-function: ease-out
                }
                7% {
                    opacity: 1;
                    transform: rotate(300deg);
                    animation-timing-function: linear;
                    origin: 0
                }
                30% {
                    opacity: 1;
                    transform: rotate(410deg);
                    animation-timing-function: ease-in-out;
                    origin: 7%
                }
                39% {
                    opacity: 1;
                    transform: rotate(645deg);
                    animation-timing-function: linear;
                    origin: 30%
                }
                70% {
                    opacity: 1;
                    transform: rotate(770deg);
                    animation-timing-function: ease-out;
                    origin: 39%
                }
                75% {
                    opacity: 1;
                    transform: rotate(900deg);
                    animation-timing-function: ease-out;
                    origin: 70%
                }
                76% {
                    opacity: 0;
                    transform: rotate(900deg)
                }
                100% {
                    opacity: 0;
                    transform: rotate(900deg)
                }
            }

            @-o-keyframes orbit {
                0% {
                    opacity: 1;
                    z-index: 99;
                    -o-transform: rotate(180deg);
                    -o-animation-timing-function: ease-out
                }
                7% {
                    opacity: 1;
                    -o-transform: rotate(300deg);
                    -o-animation-timing-function: linear;
                    -o-origin: 0
                }
                30% {
                    opacity: 1;
                    -o-transform: rotate(410deg);
                    -o-animation-timing-function: ease-in-out;
                    -o-origin: 7%
                }
                39% {
                    opacity: 1;
                    -o-transform: rotate(645deg);
                    -o-animation-timing-function: linear;
                    -o-origin: 30%
                }
                70% {
                    opacity: 1;
                    -o-transform: rotate(770deg);
                    -o-animation-timing-function: ease-out;
                    -o-origin: 39%
                }
                75% {
                    opacity: 1;
                    -o-transform: rotate(900deg);
                    -o-animation-timing-function: ease-out;
                    -o-origin: 70%
                }
                76% {
                    opacity: 0;
                    -o-transform: rotate(900deg)
                }
                100% {
                    opacity: 0;
                    -o-transform: rotate(900deg)
                }
            }

            @-ms-keyframes orbit {
                0% {
                    opacity: 1;
                    z-index: 99;
                    -ms-transform: rotate(180deg);
                    -ms-animation-timing-function: ease-out
                }
                7% {
                    opacity: 1;
                    -ms-transform: rotate(300deg);
                    -ms-animation-timing-function: linear;
                    -ms-origin: 0
                }
                30% {
                    opacity: 1;
                    -ms-transform: rotate(410deg);
                    -ms-animation-timing-function: ease-in-out;
                    -ms-origin: 7%
                }
                39% {
                    opacity: 1;
                    -ms-transform: rotate(645deg);
                    -ms-animation-timing-function: linear;
                    -ms-origin: 30%
                }
                70% {
                    opacity: 1;
                    -ms-transform: rotate(770deg);
                    -ms-animation-timing-function: ease-out;
                    -ms-origin: 39%
                }
                75% {
                    opacity: 1;
                    -ms-transform: rotate(900deg);
                    -ms-animation-timing-function: ease-out;
                    -ms-origin: 70%
                }
                76% {
                    opacity: 0;
                    -ms-transform: rotate(900deg)
                }
                100% {
                    opacity: 0;
                    -ms-transform: rotate(900deg)
                }
            }

            @-webkit-keyframes orbit {
                0% {
                    opacity: 1;
                    z-index: 99;
                    -webkit-transform: rotate(180deg);
                    -webkit-animation-timing-function: ease-out
                }
                7% {
                    opacity: 1;
                    -webkit-transform: rotate(300deg);
                    -webkit-animation-timing-function: linear;
                    -webkit-origin: 0
                }
                30% {
                    opacity: 1;
                    -webkit-transform: rotate(410deg);
                    -webkit-animation-timing-function: ease-in-out;
                    -webkit-origin: 7%
                }
                39% {
                    opacity: 1;
                    -webkit-transform: rotate(645deg);
                    -webkit-animation-timing-function: linear;
                    -webkit-origin: 30%
                }
                70% {
                    opacity: 1;
                    -webkit-transform: rotate(770deg);
                    -webkit-animation-timing-function: ease-out;
                    -webkit-origin: 39%
                }
                75% {
                    opacity: 1;
                    -webkit-transform: rotate(900deg);
                    -webkit-animation-timing-function: ease-out;
                    -webkit-origin: 70%
                }
                76% {
                    opacity: 0;
                    -webkit-transform: rotate(900deg)
                }
                100% {
                    opacity: 0;
                    -webkit-transform: rotate(900deg)
                }
            }

            @-moz-keyframes orbit {
                0% {
                    opacity: 1;
                    z-index: 99;
                    -moz-transform: rotate(180deg);
                    -moz-animation-timing-function: ease-out
                }
                7% {
                    opacity: 1;
                    -moz-transform: rotate(300deg);
                    -moz-animation-timing-function: linear;
                    -moz-origin: 0
                }
                30% {
                    opacity: 1;
                    -moz-transform: rotate(410deg);
                    -moz-animation-timing-function: ease-in-out;
                    -moz-origin: 7%
                }
                39% {
                    opacity: 1;
                    -moz-transform: rotate(645deg);
                    -moz-animation-timing-function: linear;
                    -moz-origin: 30%
                }
                70% {
                    opacity: 1;
                    -moz-transform: rotate(770deg);
                    -moz-animation-timing-function: ease-out;
                    -moz-origin: 39%
                }
                75% {
                    opacity: 1;
                    -moz-transform: rotate(900deg);
                    -moz-animation-timing-function: ease-out;
                    -moz-origin: 70%
                }
                76% {
                    opacity: 0;
                    -moz-transform: rotate(900deg)
                }
                100% {
                    opacity: 0;
                    -moz-transform: rotate(900deg)
                }
            }

            .loader {
                background: #21417A;
                position: absolute;
                top: 0;
                left: 0;
                width: 0;
                height: 0;
                overflow: hidden;
                opacity: 0;
                transition: opacity 0.5s linear;
                z-index: 9999;
            }

            .loader.enabled {
                width: 100%;
                height: 100%;
                opacity: 1
            }
        </style>
        <script>
            $(document).ready(function(){
                $('form').submit(function(){
                    $('.loader').addClass('enabled');
                });

                $('#urls').change(function() {

                    $('#install').prop('disabled', true);

                    // url="index.php?action=getLatestsVersionOptions&key=" + this.value;
                    // $.getJSON(url, function(data) {
                    //     $("#urls").empty();
                    //     $.each(data, function () {
                    //         $prevGroup = $('<optgroup />').prop('label', 'Dernière version').appendTo('#urls');
                    //         $("<option />").val(this.Value).text(this.Text).appendTo($prevGroup);
                    //     });
                    // });

                    url="index.php?action=getVersionsOptions&key=" + this.value;
                    $.getJSON(url, function(data) {
                        $("#urls").empty();
                        $.each(data, function () {
                            $prevGroup = $('<optgroup />').prop('label', 'Dernière version').appendTo('#urls');
                            $("<option />").val(this.Value).text(this.Text).appendTo($prevGroup);
                        });
                    })

                    $('#install').prop('disabled', false);

                });
            });
        </script>
    </head>

    <body>
        <div class="container">

            <div class="jumbotron text-center">
                <h1>getJoomla <small>v1.1.3 FR</small></h1>
                <p class="lead">Un script incroyable pour télécharger et préparer l'installation de Joomla!.</p>
                <p><small>
                    <a href="https://github.com/cavo789/getjoomla">https://github.com/cavo789/getjoomla</a>
                </small></p>

                <?php
                    try {
                        $installer = new Installer();

                        $installer->checkRequirements();

                        $installer->initialize();

                        $path = __DIR__ . '/joomla.zip';
                        if (file_exists($path) || isset($_GET['install'])) {
                            // Let's go, start the installation
                            $installer->prepare($_GET['install'] ?? '');
                        }
                    } catch (\Exception $exception) {
                        die('<div class="error">An error has occured: ' . $exception->getMessage() . '</div>');
                    }
                ?>
                <form action="<?php echo basename(__FILE__); ?>" method="get">
                    <div class="input-group">
                        <select class="form-control" id="urls">
                            <?php echo $installer->getURLs(); ?>
                        </select>

                        <select class="form-control" name="install" id="install">
                            <optgroup label="Dernière version">
                                <?php echo $installer->getLatestsVersionOptions();?>
                            </optgroup>
                            <optgroup label="Autre version">
                                <?php echo $installer->getVersionsOptions(); ?>
                            </optgroup>
                        </select>
                        <span class="input-group-btn">
                            <input type="submit" class="btn btn-primary" value="Installer"/>
                        </span>
                    </div><!-- /input-group -->
                </form>
            </div>

            <div class="row marketing">
                <div class="col-lg-6">
                    <h4>Le fonctionnement</h4>
                    <p>Ce script télécharge la version francophone de Joomla géré par l'AFUJ depuis
                        <a href="https://github.com/AFUJ/joomla-cms-fr/releases">le compte Github</a>, il décompresse
                        l'archive, s'autodétruit et redirige vers l'installateur de joomla.
                        En résumé : Sélectionnez et cliquez pour installer la dernière version de Joomla!.</p>
                </div>

                <div class="col-lg-6">
                    <h4>Attention</h4>
                    <p>Ce script est disponible gratuitement. Nous ne sommes pas responsable des problèmes et dommages
                        occasionnés. Nous vous conseillons d'avoir toujours une copie ou sauvegarde des fichiers
                        existant sur ce serveur.</p>

                    <h4>Licence</h4>
                    <p>Ce script est réalisé sous licence
                        <a href="http://www.gnu.org/licenses/gpl-3.0.txt">GNU/GPL 3.0</a>.
                        Gratuit pour utilisation personnelle ou commerciale.</p>
                </div>
            </div>

            <footer class="footer">
                <p>&copy; 2016 Script initial de BestProject - Traduction et adaption avec le package
                    français par Yann Gomiero, refactoring par Christophe Avonture - AFUJ</p>
            </footer>

        </div>
        <div class="loader">
            <div class="windows8">
                <div class="wBall" id="wBall_1">
                    <div class="wInnerBall"></div>
                </div>
                <div class="wBall" id="wBall_2">
                    <div class="wInnerBall"></div>
                </div>
                <div class="wBall" id="wBall_3">
                    <div class="wInnerBall"></div>
                </div>
                <div class="wBall" id="wBall_4">
                    <div class="wInnerBall"></div>
                </div>
                <div class="wBall" id="wBall_5">
                    <div class="wInnerBall"></div>
                </div>
            </div>
        </div>
    </body>
</html>
