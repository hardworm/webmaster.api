# Yandex Webmaster Api v4.1 Library

- [Installation](#installation)
    - [Composer](#composer)
    - [Manual](#manual)
- [Usage](#usage)

## Installation

### Composer

add into composer.json

```js
{
    "require": {
        ...
        "hardworm/webmaster.api": "1.1.*"
        ...
    }
}
```

or

```
php composer.phar require hardworm/webmaster.api "dev-master"
```


Also required prior to placing the package on packagist.org

```js
    ...
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/hardworm/webmaster.api"
        }
    ]
    ...
```

### Manual

Clone repository and require webmasterApi.php

## Usage

### How to use example:

1. Before use this code go to https://oauth.yandex.ru/client/new and add new client

2. Create file "example/config.php" by copy "config.example.php". Add client ID and secret code here to use example


### How to use webmasterApi class:

1. Add client on https://oauth.yandex.ru/client/new page. Don't forget to set up correct return url

2. For test you can get temporary Access Token (read comments to method webmasterApi::getAccessToken )

3. If you want to get correct Access Token, you must create authorization code.
    1. Read doc on https://tech.yandex.ru/oauth/doc/dg/reference/web-client-docpage/
    2. Locate your client to https://oauth.yandex.ru/authorize?response_type=code&client_id=[yourclient_id]
    3. Use static method getAccessToken to get access token
    4. Create webmasterApi object with static method webmasterApi::initApi

You can see example/.auth.php to see how it working

4. Enjoy!

### Methods

* `addHost`
* `addOriginalText`
* `addQueueRecrawl`
* `addSitemap`
* `checkVerification`
* `curlOpts`
* `dataToString`
* `delete`
* `deleteHost`
* `deleteOriginalText`
* `deleteSitemap`
* `errorCritical`
* `get`
* `getAccessToken`
* `getApiUrl`
* `getDiagnostics`
* `getHostInfo`
* `getHostOwners`
* `getHostSitemaps`
* `getHostSummary`
* `getHostUserSitemaps`
* `getHosts`
* `getIndexingHistory`
* `getIndexingSamples`
* `getOriginalTexts`
* `getPopularQueries`
* `getQueueRecrawl`
* `getQuotaRecrawl`
* `getSearchUrlHistory`
* `getSearchUrlSamples`
* `getSearchUrlEventHistory`
* `getSearchUrlEventHistorySamples`
* `getSqiHistory`
* `getStateRecrawlQueue`
* `getUserID`
* `initApi`
* `post`
* `verifyHost`
