<?php
/**
 * How to use webmaster api.
 *
 * Original texts
 */


// Initializtion: get config and primary classes
require_once(dirname(__FILE__) . "/.init.php");

use hardworm\webmaster\api\webmasterApi;

// Init webmaster api with your access token
$wmApi = webmasterApi::initApi($token);
if (isset($wmApi->error_message)) {
    die($wmApi->error_message);
}

// Get host_id
if (empty($_REQUEST['host_id'])) {
    webmaster_api_example_tpl::err404();
}

$hostID = $_REQUEST['host_id'];

$info = $wmApi->getHostInfo($hostID);
$info = webmaster_api_example_tpl::checkHost($info);
$history = $wmApi->getIndexingHistory($hostID);

// Если саммари - с ошибкой, это какой-то полтергейст, ибо мы уже проверили факт наличия этого хоста и его верификации
if (!empty($queries->error_code)) {
    webmaster_api_example_tpl::err500();
}
// Let's show it
webmaster_api_example_tpl::init()->header($info->unicode_host_url . ' | History');
?>

<a href="host.php?host_id=<?=$hostID?>">Общая информация</a>

<div class="hostinfo">
    <?php if (isset($history->indicators)) : ?>
        <?php foreach ($history->indicators as $httpCode => $states) : ?>
            <h2>Загруженные со статусом <?= $httpCode ?></h2>
            <span class="hostinfo_item">
            <table border="1">
                <tr>
                <?php foreach ($states as $state) { ?>
                    <th>
                        <?= date('Y-m-d', strtotime($state->date)) ?>
                    </th>
                <?php } ?>
                </tr>
                <tr>
                <?php foreach ($states as $state) { ?>
                    <td>
                        <?= $state->value ?>
                    </td>
                <?php } ?>
                </tr>
            </table>
        </span>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    $search_history = $wmApi->getSearchUrlHistory($hostID);

    if (isset($search_history->history)) :
        ?>
        <h2>Истории изменения количества страниц в поиске</h2>
        <span class="hostinfo_item">
            <table border="1">
                <tr>
                    <?php foreach ($search_history->history as $page) { ?>
                        <th><?= date('Y-m-d', strtotime($page->date))?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <?php foreach ($search_history->history as $page) { ?>
                        <td><?= $page->value ?></td>
                    <?php } ?>
                </tr>
            </table>
        </span>
    <?php endif; ?>

    <?php
    $search_event_history = $wmApi->getSearchUrlEventHistory($hostID);

    if (isset($search_event_history->indicators->APPEARED_IN_SEARCH)) :
        ?>
        <h2>Истории появления страниц в поиске</h2>
        <span class="hostinfo_item">
            <table border="1">
                <tr>
                    <?php foreach ($search_event_history->indicators->APPEARED_IN_SEARCH as $page) { ?>
                        <th><?= date('Y-m-d', strtotime($page->date))?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <?php foreach ($search_event_history->indicators->APPEARED_IN_SEARCH as $page) { ?>
                        <td><?= $page->value ?></td>
                    <?php } ?>
                </tr>
            </table>
        </span>
    <?php endif; ?>

    <?php
    if (isset($search_event_history->indicators->REMOVED_FROM_SEARCH)) :
        ?>
    <h2>Истории исключения страниц в поиске</h2>
    <span class="hostinfo_item">
            <table border="1">
                <tr>
                    <?php foreach ($search_event_history->indicators->REMOVED_FROM_SEARCH as $page) { ?>
                        <th><?= date('Y-m-d', strtotime($page->date))?></th>
                    <?php } ?>
                </tr>
                <tr>
                    <?php foreach ($search_event_history->indicators->REMOVED_FROM_SEARCH as $page) { ?>
                        <td><?= $page->value ?></td>
                    <?php } ?>
                </tr>
            </table>
        </span>
    <?php endif; ?>


    <?php
    $sqi_history = $wmApi->getSqiHistory($hostID);

    if (isset($sqi_history->points)) :
        ?>
        <h2>ИКС</h2>
        <span class="hostinfo_item">
            <table border="1">
                <tr>
                <?php foreach ($sqi_history->points as $pages) { ?>
                    <th>
                        <?= date('Y-m-d', strtotime($pages->date)) ?>
                    </th>
                <?php } ?>
                </tr>
                <tr>
                <?php foreach ($sqi_history->points as $pages) { ?>
                    <td>
                        <?= $pages->value ?>
                    </td>
                <?php } ?>
                </tr>
            </table>
        </span>
    <?php endif; ?>

</div>