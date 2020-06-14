<?php

namespace hardworm\webmaster\api;

/**
 * Class webmaster_api
 *
 * Класс является SDK к апи Яндекс.Вебмастера (api.webmaster.yandex.net).
 * Обратите внимание: В случае возникновения ошибок в некоторых ситуациях кидается сообщение в стандартный поток ошибок PHP
 *
 * Во всех случаях - методы возвращают объект, и в случае возникнования ошибки у объекта есть непустое свойство error_message и error_code, на которые нужно смотреть.
 * Так ведет себя API вебмастера, такое же поведение стараемся эмулировать в случае ошибок на уровне класса...
 *
 * Т.е., типичный вызов метода класса выглядит так:
 *
 * $hostSummary = $wmApi->getHostSummary($hostID);
 * if(!empty($hostSummary->error_code))
 * {
 *      обрабатываем ситуацию с ошибкой
 * } else
 * {
 *      обрабатываем ситуацию когда все хорошо
 * }
 *
 *
 * !! Обратите внимание!!
 * Никогда не зашивайте в коде своих программ ID хостов, пользователей и других объектов: Яндекс.Вебмастер имеет право изменить этот формат и они перестанут работать.
 * Тем более не пытайтесь самостоятельно генерировать эти ID - получайте их через функцию getHosts.
 *
 */

class webmasterApi
{

    /**
     * Access token to Webmaster Api
     *
     * Свойство заполняется при инициализации объекта
     *
     *
     * @var string
     */
    private $accessToken;

    /**
     * Url of webmaster API
     *
     * @var string
     */
    private $apiUrl = 'https://api.webmaster.yandex.net/v4.1';

    /**
     * UserID in webmaster
     *
     * @var int
     */
    public $userID;


    /**
     * Last error message
     *
     * @var string
     */
    public $lastError;

    /**
     *
     * User trigger errors
     *
     * Передавать ли возникающие ошибки в стандартный поток ошибок/
     *
     * @var boolean
     */
    public $triggerError = true;


    /**
     * webmasterApi constructor.
     *
     * Инициализирует класс работы с апи. Необходимо передать acceetoken, полученный на oauth-сервере Яндекс.
     * Обратите внимание на статический метод getAccessToken(), которую можно использовать для его получения
     *
     * @param string $accessToken  Access token from Yandex ouath serverh
     */
    protected function __construct($accessToken)
    {
        $this->accessToken = $accessToken;
        $response = $this->getUserID();
        if (isset($response->error_message)) {
            $this->errorCritical($response->error_message);
        }
        $this->userID = $response;
    }


    /**
     * webmasterApi true constructor.
     *
     * Коорректный способ создания объектов класса: При ошибке возвращает объект со стандартными ошибками.
     *
     * @param string $accessToken Access token from Yandex ouath serverh
     *
     * @return object
     */
    public static function initApi($accessToken)
    {
        $wmApi = new static($accessToken);
        if (!empty($wmApi->lastError)) {
            return (object) array('error_message' => $wmApi->lastError);
        }

        return $wmApi;
    }


    /**
     * Get handler url for this resource
     *
     * Простоая обертка, возвращающая правильный путь к ручке API
     * На самом деле все что она делает - дописывает /user/userID/, кроме, непосредственно, ручки /user/
     *
     * @param string $resource
     *
     * @return string
     */
    public function getApiUrl($resource)
    {
        $apiUrl = $this->apiUrl;
        if ($resource !== '/user/') {
            if (!$this->userID) {
                return $this->errorCritical("Can't get hand {$resource} without userID");
            }
            $apiUrl .= '/user/' . $this->userID;
        }

        return $apiUrl . $resource;
    }


    /**
     * Get request to hand
     *
     * Выполнение простого GET-запроса к ручке API.
     * В случае если переда массив $data - его значения будут записаны в запрос. Подробнее об этом массиве см. в описании
     * метода dataToString
     *
     *
     * @param string $resource  Name of api resource
     * @param array  $data      Array with request params (useful to CURLOPT_POSTFIELDS: http://php.net/curl_setopt )
     *
     * @return object
     */
    protected function get($resource, $data = array())
    {
        $apiUrl = $this->getApiUrl($resource);

        $headers = $this->getDefaultHttpHeaders();

        $url = $apiUrl . '?' . $this->dataToString($data);

        $allow_url_fopen = ini_get('allow_url_fopen');
        if (function_exists('curl_init')) {
            // Шлем запрос в курл
            $ch = curl_init($url);
            // основные опции curl
            $this->curlOpts($ch);
            // передаем заголовки
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $response = curl_exec($ch);
            $curl_error = curl_error($ch);
            curl_close($ch);
        } elseif (!empty($allow_url_fopen)) {
            $opts = array(
                'http' => array(
                    'method' => 'GET',
                    'header' => $headers
                )
            );

            # создание контекста потока
            $context = stream_context_create($opts);
            # отправляем запрос и получаем ответ от сервера
            $response = @file_get_contents($url, 0, $context);
        } else {
            return $this->errorCritical('CURL not installed & file_get_contents disabled');
        }


        if (!$response) {
            return $this->errorCritical('Error in curl when get [' . $url . '] ' . (isset($curl_error) ? $curl_error : ''));
        }
        $response = json_decode($response, false, 512, JSON_BIGINT_AS_STRING);


        if (!is_object($response)) {
            return $this->errorCritical('Unknown error in response: Not object given');
        }
        return $response;
    }


    /**
     * Post data to hand
     *
     * Выполнение POST-запроса к ручке API. Массив data передается в API как json-объект
     *
     * @param string $resource  Name of api resource
     * @param array  $data      Array with request params (useful to CURLOPT_POSTFIELDS: http://php.net/curl_setopt )
     *
     * @return false|JsonSerializable
     */
    protected function post($resource, $data)
    {
        $url = $this->getApiUrl($resource);

        $headers = $this->getDefaultHttpHeaders();

        $dataJson = json_encode($data);

        $allow_url_fopen = ini_get('allow_url_fopen');
        if (function_exists('curl_init')) {
            // Шлем запрос в курл
            $ch = curl_init($url);
            // основные опции курл
            $this->curlOpts($ch);
            // передаем заголовки
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
            $response = curl_exec($ch);
            curl_close($ch);
        } elseif (!empty($allow_url_fopen)) {
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'content' => $dataJson,
                    'header' => $headers
                )
            );

            # создание контекста потока
            $context = stream_context_create($opts);
            # отправляем запрос и получаем ответ от сервера
            $response = @file_get_contents($url, 0, $context);
        } else {
            return $this->errorCritical('CURL not installed & file_get_contents disabled');
        }

        if (!$response) {
            return $this->errorCritical('Unknown error in curl');
        }
        $response = json_decode($response);

        if (!is_object($response)) {
            return $this->errorCritical('Unknown error in curl');
        }
        return $response;
    }


    /**
     * Delete data from hand
     *
     * Выполнение DELETE запроса к  ручке API.
     *
     * @param $resource string Name of api resource
     * @param $data array Array with request params (useful to CURLOPT_POSTFIELDS: http://php.net/curl_setopt )
     * @return false|object
     */
    protected function delete($resource, $data = array())
    {
        $url = $this->getApiUrl($resource);
        $headers = $this->getDefaultHttpHeaders();
        $dataJson = json_encode($data);

        $allow_url_fopen = ini_get('allow_url_fopen');
        if (function_exists('curl_init')) {
            // Шлем запрос в курл
            $ch = curl_init($url);
            // основные опции курл
            $this->curlOpts($ch);
            // передаем заголовки
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataJson);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == '204') {
                return (object)array(true);
            }
        } elseif (!empty($allow_url_fopen)) {
            $opts = array(
                'http' => array(
                    'method' => 'DELETE',
                    'content' => $dataJson,
                    'header' => $headers
                )
            );

            # создание контекста потока
            $context = stream_context_create($opts);
            # отправляем запрос и получаем ответ от сервера
            $response = @file_get_contents($url, 0, $context);

            if (in_array('HTTP/1.1 204 No Content', $http_response_header)) {
                return (object)array(true);
            }
        } else {
            return $this->errorCritical('CURL not installed & file_get_contents disabled');
        }

        if (!$response) {
            return $this->errorCritical('Unknown error in curl');
        }

        $response = json_decode($response);
        if (!is_object($response)) {
            return $this->errorCritical('Unknown error in curl');
        }

        return $response;
    }

    protected function getDefaultHttpHeaders()
    {
        return array(
            'Authorization: OAuth ' . $this->accessToken,
            'Accept: application/json',
            'Content-type: application/json'
        );
    }
    /**
     *
     * Set Curl Options
     *
     * Устанавливаем дефолтные необходимые параметры вызова curl
     *
     * @param $ch resource curl
     *
     * @return true
     */
    protected function curlOpts(&$ch)
    {
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        return true;
    }

    /**
     * Convert post-data array to string
     *
     * простой метод, который преобразует массив в обычную query-строку.
     * Ключи - названия get-переменных, value-значение. В случае если value - массив, в итоговую строку будет записана
     * одна и та же переменная со множеством значений. Например, это актуально для вызова ручки /indexing-history/
     * в которую можно передать множетсво индикаторов, которые мы хотим передать, несколько раз задав параметр запроса indexing_indicator
     *
     * @param array $data
     * @return string
     */
    private function dataToString($data)
    {
        $queryString = array();
        foreach ($data as $param => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $queryString[] = urlencode($param) . '=' . urlencode($value);
            } elseif (is_array($value)) {
                foreach ($value as $valueItem) {
                    $queryString[] = urlencode($param) . '=' . urlencode($valueItem);
                }
            } else {
                $this->errorWarning("Bad type of key {$param}. Value must be string or array");
                continue;
            }
        }

        return implode('&', $queryString);
    }


    /**
     * Save error message and return false
     *
     * @param string $message  Text of message
     * @param bool   $json     Return false as json error
     *
     * @return false|object
     */
    private function errorCritical($message, $json = true)
    {
        $this->lastError = $message;
        if ($json) {
            if ($this->triggerError) {
                trigger_error($message, E_USER_ERROR);
            }

            return (object)array('error_code' => 'CRITICAL_ERROR', 'error_message' => $message);
        }

        return false;
    }


    /**
     * Save and log notice message and return false
     *
     * @param string $message  Text of message
     * @param bool   $json     Return false as json error
     *
     * @return false|object
     */
    private function errorWarning($message, $json = true)
    {
        $this->lastError = $message;
        if ($json) {
            if ($this->triggerError) {
                trigger_error($message, E_USER_NOTICE);
            }

            return (object)array('error_code' => 'CRITICAL_ERROR', 'error_message' => $message);
        }

        return false;
    }


    /**
     * Get User ID for current access token
     *
     * Узнать userID для текущего токена. Метод вызывается при инициализации класса, и не нужен 'снаружи':
     * Текущего пользователя можно получить через публичное свойство userID
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/user-docpage/
     *
     * @return int|false
     */
    private function getUserID()
    {
        $response = $this->get('/user/');
        if (!isset($response->user_id) || !(int) $response->user_id) {
            $message = "Can't resolve USER ID";
            if (isset($response->error_message)) {
                $message .= '. ' . $response->error_message;
            }

            return $this->errorCritical($message);
        }

        return $response->user_id;
    }


    /**
     * Add new host
     *
     * Добавление нового хоста. Параметром передается полный адрес хоста (лучше - с протоколом). Возвращается всегда объект,
     * но, в случае ошибки - объект будет содержать свойства error_code и error_message
     * В случае успеха - это будет объект со свойством host_id, содержащим ID хоста
     *
     * @param string $url
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-add-site-docpage/
     *
     * @return object Json
     */
    public function addHost($url)
    {
        return $this->post('/hosts/', array('host_url' => $url));
    }


    /**
     * Delete host from webmaster
     *
     * Удаление хоста из вебмастера. hostID - ID хоста, полученный функцией getHosts
     *
     * @param string $hostID
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-delete-docpage/
     *
     * @return object
     */
    public function deleteHost($hostID)
    {
        return $this->delete('/hosts/' . $hostID . '/');
    }


    /**
     * Get host list
     *
     * Получить список всех хостов добавленных в вебмастер для текущего пользователя.
     * Возвращается массив объектов, каждый из которых содержит данные об отдельном хосте
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-docpage/
     *
     * @return object Json
     */
    public function getHosts()
    {
        return $this->get('/hosts/');
    }

    /**
     * Check verification status of host
     *
     * Проверяем статус верификации хоста
     *
     * @param string $hostID ID of host
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-verification-get-docpage/
     *
     * @return object
     */
    public function checkVerification($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/verification/');
    }


    /**
     * Start verification of host
     *
     * Запуск процедуры верификации хоста. Обратите внимание, если запустить эту функцию для хоста, который находится
     * в процесс верификации, или же уже верифицирован - метод вернет объект с ошибкой. Проверить статус верификации можно с помощью метода
     * checkVerification
     *
     * @param string $hostID Id of host
     * @param string $type   Type of verification (DNS|HTML_FILE|META_TAG|WHOIS): get it from applicable_verifiers method of checkVerification return
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-verification-post-docpage/
     *
     * @return false|object
     */
    public function verifyHost($hostID, $type)
    {
        return $this->post('/hosts/' . $hostID . '/verification/?verification_type=' . $type, array());
    }

    /**
     * Get host info
     *
     * Получить подробную информацию об отдельном хосте
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-id-docpage/
     *
     * @return object Json
     */
    public function getHostInfo($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/');
    }

    /**
     * Get host summary info
     *
     * Метод позволяет получить подробную информацию об отдельном хосте, включая его ключевые показатели индексирования.
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-id-summary-docpage/
     *
     * @return object Json
     */
    public function getHostSummary($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/summary/');
    }

    /**
     * Get host owners
     *
     * Метод позволяет узнать всех владельцев хоста, и, для каждого из них узнать uid и метод верификации
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-owners-get-docpage/
     *
     * @return object Json
     */
    public function getHostOwners($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/owners/');
    }

    /**
     * Get host sitemaps
     *
     * Узнать список всех файлов sitemap, которые используются роботами при обходе данного хоста.
     * Если передается параметр parentID, вернется список файлов, которые относятся к файлу sitemap index с этим id
     * Если параметр не задан - вернутся все файлы, которые лежат в корне.
     *
     * Обратите внимание: Метод не возвращает те файлы, которые добавлены через Яндекс.Вебмастер но еще не используются
     * при обходе. Для получения списка этих файлов используйте метод getHostUserSitemaps
     *
     * @param string $hostID   Host id in webmaster
     * @param string $parentID Id of parent sitemap
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-sitemaps-get-docpage/
     *
     * @return object Json
     */
    public function getHostSitemaps($hostID, $parentID = null)
    {
        $get = array();
        if ($parentID) {
            $get['parent_id'] = $parentID;
        }

        return $this->get('/hosts/' . $hostID . '/sitemaps/', $get);
    }


    /**
     * Get list of user added sitemap files
     *
     * Метод позволяет получить список все файлов sitemap, добавленных через Яндекс.Вебмастер или API
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-user-added-sitemaps-get-docpage/
     *
     * @return object Json
     */
    public function getHostUserSitemaps($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/user-added-sitemaps/');
    }

    /**
     * Add new sitemap
     *
     * Добаление новой карты сайта
     *
     * @param string $hostID Host id in webmaster
     * @param string $url    URL with new sitemap
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-user-added-sitemaps-post-docpage/
     *
     * @return object
     */
    public function addSitemap($hostID, $url)
    {
        return $this->post('/hosts/' . $hostID . '/user-added-sitemaps/', array('url' => $url));
    }

    /**
     * Delete host user-added sitemap
     *
     * Удаление существующего файла sitemap.
     * Обратите внимание, что удалять можно только те файлы, которые были вручную добавлены через вебмастер или апи вебмастера
     * (эти файлы можно получить через метод getHostUserSitemaps).
     *
     * Файлы добавленные через robots.txt удалить этим методом нельзя.
     *
     * @param string $hostID     Host id in webmaster
     * @param string $sitemapId  Sitemap ID
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-user-added-sitemaps-sitemap-id-delete-docpage/
     *
     * @return object Json
     */
    public function deleteSitemap($hostID, $sitemapId)
    {
        return $this->delete('/hosts/' . $hostID . '/user-added-sitemaps/' . $sitemapId . '/');
    }


    /**
     * Get Indexing history
     *
     * По умолчанию - вытаскивается статистика за последний месяц. Период можно изменить передав соответствующие timestamps в параметрах
     * $date_from и $date_to
     *
     * @param string   $hostID   Host id in webmaster
     * @param int|null $dateFrom Date from in timestamp
     * @param int|null $dateTo   Date to in timestamp
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-indexing-history-docpage/
     *
     * @return object Json
     */
    public function getIndexingHistory($hostID, $dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) {
            $dateFrom = strtotime('-1 month');
        }
        if (!$dateTo) {
            $dateTo = time();
        }
        if (!is_int($dateTo) || !$dateTo) {
            return $this->errorCritical("Bad timestamp to {$dateTo}");
        }
        if (!is_int($dateFrom) || !$dateFrom) {
            return $this->errorCritical("Bad timestamp to {$dateFrom}");
        }
        if ($dateTo < $dateFrom) {
            return $this->errorCritical("Date to can't be smaller then Date from");
        }

        return $this->get('/hosts/' . $hostID . '/indexing/history/', array('date_from' => date(DATE_ATOM, $dateFrom), 'date_to' => date(DATE_ATOM, $dateTo)));
    }

    /**
     * Get samples of loaded pages
     *
     * Возвращает URL страниц, участвующих в результатах поиска — до 50 000
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset Offset list. The minimum value is 0
     * @param int    $limit  The size of the page (1-100)
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-indexing-samples-docpage/
     *
     * @return object Json
     */
    public function getIndexingSamples($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit)) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/indexing/samples/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Getting the history of changing the number of pages in the search
     *
     * Возвращает количество страниц в поиске за определенный период времени. По умолчанию возвращаются данные за текущий месяц.
     *
     * @param string   $hostID   Host id in webmaster
     * @param int|null $dateFrom Date from in timestamp
     * @param int|null $dateTo   Date to in timestamp
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-indexing-insearch-history-docpage/
     *
     * @return object Json
     */
    public function getSearchUrlHistory($hostID, $dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) {
            $dateFrom = strtotime('-1 month');
        }
        if (!$dateTo) {
            $dateTo = time();
        }
        if (!is_int($dateTo)) {
            return $this->errorCritical("Bad timestamp to {$dateTo}");
        }
        if (!is_int($dateFrom)) {
            return $this->errorCritical("Bad timestamp to {$dateFrom}");
        }
        if ($dateTo < $dateFrom) {
            return $this->errorCritical("Date to can't be smaller then Date from");
        }

        return $this->get('/hosts/' . $hostID . '/search-urls/in-search/history/', array('date_from' => date(DATE_ATOM, $dateFrom), 'date_to' => date(DATE_ATOM, $dateTo)));
    }

    /**
     * Getting sample pages in a search
     *
     * Возвращает URL страниц, участвующих в результатах поиска — до 50 000.
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset Offset list. The minimum value is 0
     * @param int    $limit  The size of the page (1-100)
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-indexing-insearch-samples-docpage/#hosts-indexing-insearch-samples
     *
     * @return object Json
     */
    public function getSearchUrlSamples($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/search-urls/in-search/samples/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Getting the history of the appearance and exclusion of pages from the search
     *
     * Возвращает количество страниц, появившихся в поиске и исключенных из него, за определенный период. По умолчанию возвращаются данные за 1 месяц
     *
     * @param string   $hostID   Host id in webmaster
     * @param null|int $dateFrom Date from in timestamp
     * @param null|int $dateTo   Date to in timestamp
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-search-events-history-docpage/#hosts-search-events-history
     *
     * @return object Json
     */
    public function getSearchUrlEventHistory($hostID, $dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) {
            $dateFrom = strtotime('-1 month');
        }
        if (!$dateTo) {
            $dateTo = time();
        }
        if (!is_int($dateTo)) {
            return $this->errorCritical("Bad timestamp to {$dateTo}");
        }
        if (!is_int($dateFrom)) {
            return $this->errorCritical("Bad timestamp to {$dateFrom}");
        }
        if ($dateTo < $dateFrom) {
            return $this->errorCritical("Date to can't be smaller then Date from");
        }

        return $this->get('/hosts/' . $hostID . '/search-urls/events/history/', array('date_from' => date(DATE_ATOM, $dateFrom), 'date_to' => date(DATE_ATOM, $dateTo)));
    }

    /**
     * View examples of appeared and deleted pages from the search
     *
     * Возвращает URL страниц, появившихся в поиске или исключенных из него — до 50 000.
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset Offset list. The minimum value is 0
     * @param int    $limit  The size of the page (1-100)
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/hosts-search-events-samples-docpage/#hosts-search-events-samples
     *
     * @return object Json
     */
    public function getSearchUrlEventHistorySamples($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/search-urls/events/history/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Site Diagnostics
     *
     * Возвращает информацию об ошибках на сайте
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-diagnostics-get-docpage/#host-diagnostics-get
     *
     * @return object
     */
    public function getDiagnostics($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/diagnostics/');
    }

    /**
     * Retrieving a list of rescheduling tasks
     *
     * Возвращает список задач на переобход страниц сайта.
     *
     * @param string   $hostID   Host id in webmaster
     * @param null|int $dateFrom Date from in timestamp
     * @param null|int $dateTo   Date to in timestamp
     * @param int      $offset   Offset list. The minimum value is 0
     * @param int      $limit    The size of the page (1-100)
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-recrawl-get-docpage/#host-recrawl-get
     *
     * @return object
     */
    public function getQueueRecrawl($hostID, $dateFrom = null, $dateTo = null, $offset = 0, $limit = 100)
    {
        if (!$dateFrom) {
            $dateFrom = strtotime('-1 month');
        }
        if (!$dateTo) {
            $dateTo = time();
        }
        if (!is_int($dateTo)) {
            return $this->errorCritical("Bad timestamp to {$dateTo}");
        }
        if (!is_int($dateFrom)) {
            return $this->errorCritical("Bad timestamp to {$dateFrom}");
        }
        if ($dateTo < $dateFrom) {
            return $this->errorCritical("Date to can't be smaller then Date from");
        }

        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/recrawl/queue/', array('offset' => $offset, 'limit' => $limit, 'date_from' => date(DATE_ATOM, $dateFrom), 'date_to' => date(DATE_ATOM, $dateTo)));
    }

    /**
     * Adding a site page to rerun
     *
     * Отправляет URL на переобход
     *
     * @param string $hostID Host id in webmaster
     * @param string $url    URL of the page being sent for rerun
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-recrawl-post-docpage/#host-recrawl-post
     *
     * @return object
     */
    public function addQueueRecrawl($hostID, $url)
    {
        return $this->post('/hosts/' . $hostID . '/recrawl/queue/', array('url' => $url));
    }

    /**
     * Check quota for overshoot
     *
     * Возвращает суточную квоту на переобход страниц сайта
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-recrawl-quota-get-docpage/#host-recrawl-quota-get
     *
     * @return object
     */
    public function getQuotaRecrawl($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/recrawl/quota/');
    }

    /**
     * Checking the status of a rescheduling task
     *
     * Вовзращает статус задачи на переобход страницы сайта
     *
     * @param string $hostID Host id in webmaster
     * @param string $taskID Relocation task ID
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-recrawl-task-get-docpage/#host-recrawl-task-get
     *
     * @return object
     */
    public function getStateRecrawlQueue($hostID, $taskID)
    {
        return $this->get('/hosts/' . $hostID . '/recrawl/queue/' . $taskID);
    }

    /**
     * Get Sqi history
     *
     * Получить историю Икс
     * По умолчанию - вытаскивается статистика за последний месяц. Период можно изменить передав соответствующие timestamps в параметрах
     * $date_from и $date_to
     *
     * @param string   $hostID   Host id in webmaster
     * @param int|null $dateFrom Date from in timestamp
     * @param int|null $dateTo   Date to in timestamp
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/sqi-history-docpage/
     *
     * @return object Json
     */
    public function getSqiHistory($hostID, $dateFrom = null, $dateTo = null)
    {
        if (!$dateFrom) {
            $dateFrom = strtotime('-1 month');
        }
        if (!$dateTo) {
            $dateTo = time();
        }
        if (!is_int($dateTo)) {
            return $this->errorCritical("Bad timestamp to {$dateTo}");
        }
        if (!is_int($dateFrom)) {
            return $this->errorCritical("Bad timestamp to {$dateFrom}");
        }
        if ($dateTo < $dateFrom) {
            return $this->errorCritical("Date to can't be smaller then Date from");
        }

        return $this->get('/hosts/' . $hostID . '/sqi-history/', array('date_from' => date(DATE_ATOM, $dateFrom), 'date_to' => date(DATE_ATOM, $dateTo)));
    }

    /**
     * Get External links history
     *
     * Получение истории изменения количества внешних ссылок на сайт
     *
     * @param string $hostID    Host id in webmaster
     * @param string $indicator Number of external links indicator
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-links-external-history-docpage/
     *
     * @return object Json
     */
    public function getExternalLinksHistory($hostID, $indicator = 'LINKS_TOTAL_COUNT')
    {
        return $this->get('/hosts/' . $hostID . '/links/external/history/', array('indicator' => $indicator));
    }

    /**
     * Get TOP-500 popular queries from host
     *
     * Получить TOP-500 популярных запросов.
     *
     * @param string   $hostID              Host id in webmaster
     * @param string   $orderBy             Ordering: TOTAL_CLICKS|TOTAL_SHOWS
     * @param array    $queryIndicator      array('TOTAL_SHOWS','TOTAL_CLICKS','AVG_SHOW_POSITION','AVG_CLICK_POSITION')
     * @param array    $deviceTypeIndicator Device type array('ALL', 'DESKTOP', 'MOBILE_AND_TABLET', 'MOBILE', 'TABLET') Default value: ALL.
     * @param null|int $dateFrom            The start date of the range. If omitted, data is returned for the last week
     * @param null|int $dateTo              The end date of the range. If omitted, data is returned for the last week
     * @param int      $offset              The list offset. The minimum value is 0. Default value: 0
     * @param int      $limit               Page size (1-500). Default value: 500
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-search-queries-popular-docpage/
     *
     * @return object Json
     */
    public function getPopularQueries($hostID, $orderBy = 'TOTAL_CLICKS', $queryIndicator = array(), $deviceTypeIndicator = array(), $dateFrom = null, $dateTo = null, $offset = 0, $limit = 100)
    {
        return $this->get(
            '/hosts/' . $hostID . '/search-queries/popular/',
            array(
                'order_by'              => $orderBy,
                'query_indicator'       => $queryIndicator,
                'device_type_indicator' => $deviceTypeIndicator,
                'date_from'             => $dateFrom,
                'date_to'               => $dateTo,
                'offset'                => $offset,
                'limit'                 => $limit
            )
        );
    }

    /**
     * Getting general statistics for all search queries
     *
     * Получение общей статистики по всем поисковым запросам
     *
     * @param string   $hostID               Host id in webmaster
     * @param array    $queryIndicators      array('TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION')
     * @param array    $deviceTypeIndicator  array('ALL', 'DESKTOP', 'MOBILE_AND_TABLET', 'MOBILE', 'TABLET')
     * @param null|int $dateFrom             Date from in timestamp
     * @param null|int $dateTo               Date to in timestamp
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-search-queries-history-all-docpage/#host-search-queries-history-all
     *
     * @return object Json
     */
    public function getAllQueries($hostID, $queryIndicators = array(), $deviceTypeIndicator = array(), $dateFrom = null, $dateTo = null)
    {
        return $this->get(
            '/hosts/' . $hostID . '/search-queries/all/history/',
            array('query_indicator' => $queryIndicators, 'device_type_indicator' => $deviceTypeIndicator, 'date_from' => $dateFrom, 'date_to' => $dateTo)
        );
    }

    /**
     * Getting general statistics for a search query
     *
     * Получение общей статистики по поисковому запросу
     *
     * @param string   $hostID              Host id in webmaster
     * @param string   $queryId             Search query ID
     * @param array    $queryIndicators     array('TOTAL_SHOWS', 'TOTAL_CLICKS', 'AVG_SHOW_POSITION', 'AVG_CLICK_POSITION')
     * @param array    $deviceTypeIndicator array('ALL', 'DESKTOP', 'MOBILE_AND_TABLET', 'MOBILE', 'TABLET')
     * @param null|int $dateFrom            Date from in timestamp
     * @param null|int $dateTo              Date to in timestamp
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-search-queries-history-docpage/#host-search-queries-history
     *
     * @return object
     */
    public function getQueriesById($hostID, $queryId, $queryIndicators = array(), $deviceTypeIndicator = array(), $dateFrom = null, $dateTo = null)
    {
        return $this->get(
            '/hosts/' . $hostID . '/search-queries/' . $queryId . '/',
            array('query_indicator' => $queryIndicators, 'device_type_indicator' => $deviceTypeIndicator, 'date_from' => $dateFrom, 'date_to' => $dateTo)
        );
    }

    /**
     * Get original texts from host
     *
     * Получить список всех оригинальных текстов для заданного хоста.
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset
     * @param int    $limit
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-original-texts-get-docpage/
     *
     * @return object Json
     */
    public function getOriginalTexts($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/original-texts/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Add new original text to host
     *
     * Добавить оригинальный текст.
     * Здесь мы не проверяем размер текста, т.к. эти ошибки вернет само API.
     * Теоретически требования к ОТ могут меняться, потому неправильно поддерживать это в клиентской библиотеке
     *
     * @param string $hostID  Host id in webmaster
     * @param string $content Text to add
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-original-texts-post-docpage/
     *
     * @return object Json
     */
    public function addOriginalText($hostID, $content)
    {
        return $this->post('/hosts/' . $hostID . '/original-texts/', array('content' => $content));
    }

    /**
     * Delete existing original text from host
     *
     * Удалить сущестующий ОТ для хоста
     *
     * @param string $hostID  Host id in webmaster
     * @param string $textId  Text ID to delete
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-original-texts-delete-docpage/
     *
     * @return object Json
     */
    public function deleteOriginalText($hostID, $textId)
    {
        return $this->delete('/hosts/' . $hostID . '/original-texts/' . urlencode($textId) . '/');
    }

    /**
     * Getting information about external links to the site
     *
     * Возвращает примеры внешних ссылок на страницы сайта
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset Offset in the list. The minimum value is 0. Default value: 0.
     * @param int    $limit  Page size (1-100). Default value: 10.
     *
     * @link https://tech.yandex.ru/webmaster/doc/dg/reference/host-links-external-samples-docpage/
     *
     * @return object
     */
    public function getExternalLinks($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/links/external/samples/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Getting information about broken internal links on the site
     *
     * Получение информации о неработающих внутренних ссылках сайта
     *
     * @param string $hostID    Host id in webmaster
     * @param array  $indicator The broken link indicator
     * @param int    $offset    Offset in the list. The minimum value is 0. Default value: 0.
     * @param int    $limit     Page size (1-100). Default value: 10.
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-links-internal-samples-docpage/#host-links-internal-samples
     *
     * @return object
     */
    public function getBrokenLinks($hostID, array $indicator = array(), $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/links/internal/broken/samples', array('offset' => $offset, 'limit' => $limit, 'indicator' => $indicator));
    }

    /**
     * Getting the history of changes in the number of broken internal links on the site
     *
     * Получение истории изменения количества неработающих внутренних ссылок сайта
     *
     * @param string $hostID Host id in webmaster
     * @param int    $offset Offset list. The minimum value is 0
     * @param int    $limit  Page size (1-100). Default value: 10.
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-links-internal-history-docpage/#host-links-internal-history
     *
     * @return object
     */
    public function getBrokenLinksHistory($hostID, $offset = 0, $limit = 100)
    {
        if (!is_int($offset)) {
            return $this->errorCritical("Bad offset to {$offset}");
        }

        if (!is_int($limit) || $limit > 100 || $limit < 0) {
            return $this->errorCritical("Bad limit to {$limit}");
        }

        return $this->get('/hosts/' . $hostID . '/links/internal/broken/history/', array('offset' => $offset, 'limit' => $limit));
    }

    /**
     * Getting the history of changes to an important page
     *
     * Мониторинг важных страниц
     *
     * @param string $hostID Host id in webmaster
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-id-important-urls-docpage/
     *
     * @return object
     */
    public function getImportantUrls($hostID)
    {
        return $this->get('/hosts/' . $hostID . '/important-urls');
    }

    /**
     * Getting the history of changes to an important page
     *
     * Получение истории изменений важной страницы
     *
     * @param string $hostID Host id in webmaster
     * @param string $url    The URL of the page you want to get information about
     *
     * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-id-important-urls-history-docpage/#host-id-important-urls-history
     *
     * @return object
     */
    public function getImportantUrlsHistory($hostID, $url)
    {
        return $this->get('/hosts/' . $hostID . '/important-urls/history/', array('url' => $url));
    }

    /**
     * Get Access token by code and client secret
     *
     * How to use:
     * 1. Go to https://oauth.yandex.ru/client/new
     * 2. Type name of program
     * 3. Select "Яндекс.Вебмастер" in rules section
     * 4. Select both checkboxes
     * 5. In Callback url write: "https://oauth.yandex.ru/verification_code"
     * 6. Save it
     * 7. Remember your client ID and client Secret
     * 8. Go to https://oauth.yandex.ru/authorize?response_type=code&client_id=[Client_ID]
     * 9. Remember your code
     * 10. Use this function to get access token
     * 11. Remember it
     * 12. Enjoy!
     *
     *
     * @deprecated This function is deprecated. It's only for debug
     *
     *
     * @param $code
     * @param $clientId
     * @param $clientSecret
     * @return object
     */
    public static function getAccessToken($code, $clientId, $clientSecret)
    {
        $postData = array(
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        );

        $ch = curl_init('https://oauth.yandex.ru/token');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);


        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            die('Unknown error in curl');
        }

        $response = json_decode($response);

        if (!is_object($response)) {
            die('Unknown error in curl');
        }

        return $response;
    }
}
