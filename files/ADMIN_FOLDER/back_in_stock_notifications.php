<?php //product_name_extra to add

declare(strict_types=1);

//todo: striped table css, change CEON css/ids/classes -> ZC standard
//todo: POSM support

/** phpstorm inspections
 * @var currencies $currencies
 * @var queryFactory $db
 * @var messageStack $messageStack
 * @var notifier $zco_notifier
 * @var template_func $template
 * @var $current_page_base
 */
/**
 * Ceon Back In Stock Notifications Admin Utility.
 *
 * @package     ceon_back_in_stock_notifications
 * @author      Conor Kerr <zen-cart.back-in-stock-notifications@dev.ceon.net>
 * @author      Tony Niemann <tony@loosechicken.com>
 * @copyright   Copyright 2004-2012 Ceon
 * @copyright   Portions Copyright 2008 RubikIntegration team @ RubikIntegration.com
 * @copyright   Portions Copyright 2003-2006 Zen Cart Development Team
 * @copyright   Portions Copyright 2003 osCommerce
 * @link        https://www.ceon.net
 * @license     http://www.gnu.org/copyleft/gpl.html   GNU Public License V2.0
 * @version     $Id: back_in_stock_notifications.php 2023-11-11 torvista
 */

// FOR REPEAT TESTING ONLY
// normally true: only set to false to NOT delete the subscriptions after sending the (real) emails in Option 4, to allow repeat testing but not have to keep adding the subscriptions again.
//todo make an admin option
$delete_customer_subscriptions = true;

//////////////////////////////////////
const CEON_BACK_IN_STOCK_NOTIFICATIONS_VERSION = '3.2.3alpha';

require('includes/application_top.php');

$languages = !empty($languages) ? $languages : zen_get_languages();// todo check in case $languages not yet been set

// Check the database subscriptions table exist. If not, run the installer
$table_exists_query = 'SHOW TABLES LIKE "' . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . '"';
$table_exists_result = $db->Execute($table_exists_query);

// Check all the admin options are created
$option_missing =
    !defined('BACK_IN_STOCK_NOTIFICATIONS_ENABLED') ||
    !defined('BACK_IN_STOCK_REQUIRES_LOGIN') ||
    !defined('SEND_EXTRA_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTION_EMAILS_TO');

//can use parameter on page url &check-config=1 to force check
if ($table_exists_result->EOF || $option_missing || isset($_GET['check-config'])) {
    // Instantiate and run the installation/upgrade class
    require_once(DIR_WS_CLASSES . 'class.CeonBISNInstallOrUpgrade.php');
    $install_or_upgrade = new CeonBISNInstallOrUpgrade();

    if (count($install_or_upgrade->error_messages) > 0) {
        foreach ($install_or_upgrade->error_messages as $error_message) {
            print '<p style="background: #fcc; border: 1px solid #f00; margin: 1em;' .
                ' padding: 0.4em;">Error: ' . $error_message . "</p>\n";
        }
        require(DIR_WS_INCLUDES . 'application_bottom.php');
        exit;
    }
}

require_once(DIR_WS_FUNCTIONS . 'plugin_bisn_functions.php');

$bisn_options = [
    0 => [
        'id' => 1,
        'text' => '1: ' . TEXT_LIST_ALL_SUBSCRIBED_PRODUCTS
    ],
    1 => [
        'id' => 2,
        'text' => '2: ' . TEXT_LIST_ALL_SUBSCRIPTIONS
    ],
    2 => [
        'id' => 3,
        'text' => '3: ' . TEXT_PREVIEW_NOTIFICATION_EMAILS
    ],
    3 => [
        'id' => 4,
        'text' => '4: ' . TEXT_SEND_NOTIFICATION_EMAILS
    ],
    4 => [
        'id' => 5,
        'text' => '5: ' . TEXT_REMOVE_DELETED_PRODUCTS
    ]
];

if (empty($_GET['option']) || !is_numeric($_GET['option']) || (int)$_GET['option'] < 1 || (int)$_GET['option'] > 5) {
    $_GET['option'] = 1;
}
$option = (int)$_GET['option'];

switch ($option) {
    case 1://list the products that have subscriptions attached

        //for deleting all subscriptions to one product
        //todo review this
        if (isset($_POST['delete'])) {
            $delete_product_subscriptions_query_raw = "DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE product_id = " . (int)$_POST['delete'];
            $db->Execute($delete_product_subscriptions_query_raw);
        }

        $products_query_raw = "
			SELECT
				bisns.product_id, pd.products_name, p.products_model, COUNT(*) AS num_subscribers, p.products_type,
				p.products_quantity AS current_stock, cd.categories_name, cd.categories_id
			FROM
				" . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " bisns
			LEFT JOIN
				" . TABLE_PRODUCTS_DESCRIPTION . " pd
			ON
				(pd.products_id = bisns.product_id
			AND
				pd.language_id = '" . $_SESSION['languages_id'] . "')
			LEFT JOIN
				" . TABLE_PRODUCTS . " p
			ON
				p.products_id = pd.products_id
			LEFT JOIN
				" . TABLE_CATEGORIES_DESCRIPTION . " cd
			ON
				(p.master_categories_id = cd.categories_id
			AND
				cd.language_id = '" . $_SESSION['languages_id'] . "')
			WHERE
				1 = 1
			GROUP BY
				bisns.product_id, pd.products_name, p.products_model, p.products_type, p.products_quantity, cd.categories_name, cd.categories_id";

        $sort_column = $_GET['sort'] ?? 'model'; //set default sort column

        switch ($sort_column) {
            case 'category':
                $products_query_raw .= ' ORDER BY cd.categories_name';
                break;
            case 'model':
                $products_query_raw .= ' ORDER BY p.products_model';//steve extra column
                break;//steve extra column
            case 'product':
                $products_query_raw .= ' ORDER BY pd.products_name';
                break;
            case 'subscribers':
                $products_query_raw .= ' ORDER BY num_subscribers DESC';
                break;
            case 'stock':
                $products_query_raw .= ' ORDER BY p.products_quantity DESC';
                break;
            default:
                $products_query_raw .= ' ORDER BY cd.categories_name, pd.products_name';
        }

        $products_query_raw = str_replace(["\n", "\r", "\t"], ' ', $products_query_raw);

        if (isset($_GET['page']) && (int)$_GET['page'] !== -1) { //todo check strict
            $products_split = new splitPageResults(
                $_GET['page'],
                MAX_DISPLAY_SEARCH_RESULTS_REPORTS, $products_query_raw, $num_rows
            );
        }

        $product_subscriptions_info = $db->Execute($products_query_raw);

        // Get accurate value for the number of rows
        $num_rows_query = "
			SELECT
				bisns.id
			FROM
				" . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " bisns
			WHERE
				1 = 1;";

        $num_rows_result = $db->Execute($num_rows_query);
        $num_rows = $num_rows_result->RecordCount();
        break;

    case 2://list all the subscriptions, by customer

        //for deleting a single subscription to one product
        if (isset($_POST['delete'])) {
            $delete_customer_subscription_query_raw = "DELETE FROM " . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " WHERE id = " . (int)$_POST['delete'];
            $db->Execute($delete_customer_subscription_query_raw);
        }

        $subscriptions_query_raw = "
			SELECT
				DISTINCT bisns.id, bisns.customer_id, bisns.product_id, bisns.name, bisns.email_address,
				bisns.date_subscribed, pd.products_name, p.products_type, c.customers_firstname, c.customers_lastname, c.customers_email_address,
				cd.categories_name, cd.categories_id, p.products_model, bisns.languages_id 
			FROM
				" . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " bisns
			LEFT JOIN
				" . TABLE_PRODUCTS_DESCRIPTION . " pd
			ON
				(pd.products_id = bisns.product_id
			AND
				pd.language_id = '" . $_SESSION['languages_id'] . "')
			LEFT JOIN
				" . TABLE_PRODUCTS . " p
			ON
				p.products_id = pd.products_id
			LEFT JOIN
				" . TABLE_CUSTOMERS . " c
			ON
				c.customers_id = bisns.customer_id
			LEFT JOIN
				" . TABLE_CATEGORIES_DESCRIPTION . " cd
			ON
				(p.master_categories_id = cd.categories_id
			AND
				cd.language_id = '" . $_SESSION['languages_id'] . "')
			WHERE
				1 = 1";

        $sort_column = $_GET['sort'] ?? 'product';

        switch ($sort_column) {//steve edited for model instead of category
            /*case 'category':
                $subscriptions_query_raw .= ' ORDER BY cd.categories_name, pd.products_name,' .
                    ' bisns.date_subscribed DESC';
                break;*/
            case 'model':
                $subscriptions_query_raw .= ' ORDER BY p.products_model,' .
                    ' bisns.date_subscribed DESC';
                break;
            case 'product':
                $subscriptions_query_raw .= ' ORDER BY pd.products_name';
                break;
            case 'date':
                $subscriptions_query_raw .= ' ORDER BY bisns.date_subscribed DESC,' .
                    'cd.categories_name, pd.products_name';
                break;
            case 'customer_name':
                $subscriptions_query_raw .= ' ORDER BY bisns.name, bisns.date_subscribed DESC,' .
                    ' cd.categories_name, pd.products_name';
                break;
            case 'customer_email':
                $subscriptions_query_raw .= ' ORDER BY bisns.email_address,' .
                    ' c.customers_email_address, bisns.date_subscribed DESC,' .
                    ' cd.categories_name, pd.products_name';
                break;
            case 'languages_id':
                $subscriptions_query_raw .= ' ORDER BY bisns.languages_id,' .
                    ' bisns.date_subscribed DESC';
                break;
            //eof
            default:
                //steve changed to model
                //$subscriptions_query_raw .= ' ORDER BY pd.products_name,' .
                //    ' bisns.date_subscribed DESC';
                $subscriptions_query_raw .= ' ORDER BY p.products_model,' .
                    ' bisns.date_subscribed DESC';
        }

        $subscriptions_query_raw = str_replace(["\n", "\r", "\t"], ' ', $subscriptions_query_raw);

        if (isset($_GET['page']) && (int)$_GET['page'] !== -1) {//todo check strict
            $subscriptions_split = new splitPageResults(
                $_GET['page'],
                MAX_DISPLAY_SEARCH_RESULTS_REPORTS, $subscriptions_query_raw, $num_rows
            );
        }

        $subscriptions_info = $db->Execute($subscriptions_query_raw);

        // Get accurate value for the number of rows
        $num_rows_query = "
			SELECT
				bisns.id
			FROM
				" . TABLE_BACK_IN_STOCK_NOTIFICATION_SUBSCRIPTIONS . " bisns
			WHERE
				1 = 1;";
        $num_rows_result = $db->Execute($num_rows_query);
        $num_rows = $num_rows_result->RecordCount();
        break;

    case 3://for the test run, show all the emails that need to be sent

        //todo needs rework
        unset($send_output);
        foreach ($languages as $key => $lang) {
            $send_output[$lang['id']] = sendBackInStockNotifications((int)$lang['id'], true);//test mode == true: emails sent only to EMAIL_FROM (store address). Subscriptions not deleted.
        }
        break;

    case 4://for the real sending, send only those with the same admin language to use the correct email constants

        //todo needs rework
        unset($send_output);
        $send_output[$_SESSION['languages_id']] = sendBackInStockNotifications((int)$_SESSION['languages_id'], '', $delete_customer_subscriptions);
        break;

    case 5:
        expungeOutdatedSubscriptionsFromBackInStockNotificationsDB();
        break;
}

?>
<!doctype html>
<html <?php echo HTML_PARAMS; ?>>
<head>
    <?php require DIR_WS_INCLUDES . 'admin_html_head.php'; ?>
</head>
<body>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<!-- body //-->
<div id="ceon-bisn-wrapper">
    <h1 id="ceon-bisn-page-heading"><?php echo BACK_IN_STOCK_NOTIFICATIONS_HEADING_TITLE; ?></h1>
    <?php echo(!$delete_customer_subscriptions ? TEXT_DEBUG_NO_DELETE_SUBSCRIPTIONS : ''); ?>

    <div class="SpacerSmall"></div>

    <ul id="ceon-panels-menu">
        <li id="transaction-action-tab" class="CeonPanelTabSelected">
            <a href="#"><?php echo BACK_IN_STOCK_NOTIFICATIONS_HEADING_TITLE; ?></a>
        </li>
    </ul>
    <div id="ceon-panels-wrapper">
        <fieldset id="main-panel" class="CeonPanel">
            <legend class="DisplayNone"><?php echo BACK_IN_STOCK_NOTIFICATIONS_HEADING_TITLE; ?></legend>
            <!-- body_text //-->
            <div id="actionSelector">
                <?php
                echo zen_draw_form('back_in_stock_notifications', FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, '', 'GET') .
                    zen_hide_session_id(); ?>
                <fieldset class="DoubleSpaceBelow">
                    <legend><?php echo TEXT_ACTION_TO_PERFORM; ?></legend>
                    <input type="hidden" name="action" value="send"/>
                    <?php
                    echo zen_draw_pull_down_menu('option', $bisn_options, $option); ?>
                    <input type="submit" value="<?php
                    echo TEXT_SUBMIT_GO; ?>"/>
                </fieldset>
                <?php echo '</form>'; ?>
            </div>
            <?php
            if ($option === 1 || $option === 2) {
                //steve for info text
//todo rework
                if ($option === 1) { ?>
                    <div><?= TEXT_NOTE_OPTION_1; ?></div>
                <?php
                }
                if ($option === 2) { ?>
                    <div><?= TEXT_NOTE_OPTION_1; ?></div>
                <?php
                }

                // Build the listings page count and page links code
                if (isset($products_split)) {
                    $split_object = $products_split;
                    $count_text = TEXT_DISPLAY_NUMBER_OF_PRODUCTS;
                } else {
                    if (isset($subscriptions_split)) {
                        $split_object = $subscriptions_split;
                    }
                    $count_text = TEXT_DISPLAY_NUMBER_OF_BACK_IN_STOCK_NOTIFICATIONS;
                }

                if (isset($_GET['page']) && (int)$_GET['page'] !== -1) {//todo check strict
                    // Page is to be split according to the maximum rows per page
                    $pagination_columns = '<table style="width:100%">' .
                        '<tr><td class="BISNPageCount">' .
                        $split_object->display_count(
                            $num_rows,
                            MAX_DISPLAY_SEARCH_RESULTS_REPORTS,
                            $_GET['page'],
                            $count_text
                        ) . '</td>' . "\n";

                    $pagination_columns .= '<td class="BISNPageLinks">' .
                        $split_object->display_links(
                            $num_rows,
                            MAX_DISPLAY_SEARCH_RESULTS_REPORTS,
                            MAX_DISPLAY_PAGE_LINKS,
                            $_GET['page'],
                            zen_get_all_get_params(['page', 'action'])
                        );

                    $pagination_columns .= ' [<a href="' . zen_href_link(
                            FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                            zen_get_all_get_params(['page', 'action']) . 'page=-1'
                        ) . '">' . TEXT_SHOW_ALL .
                        '</a>]' . '</td>' . "\n";

                    $pagination_columns .= '</tr></table>' . "\n";
                } else {//page=-1: show all
                    // All results are to be shown regardless of any maximum rows per page setting
                    $pagination_columns = '<table style="width:100%">' . 
                        '<tr><td class="BISNPageCount">' .
                        sprintf($count_text, 1, $num_rows, $num_rows) . '</td>' . "\n";

                    $pagination_columns .= '<td class="BISNPageLinks">' .
                        '<a href="' . zen_href_link(
                            FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                            zen_get_all_get_params(['page', 'action']) . 'page=1'
                        ) . '">' .
                        TEXT_DISPLAY_BY_PAGE . '</a>' . '</td>' . "\n";

                    $pagination_columns .= '</tr></table>' . "\n";
                }
            }

            if ($option === 1) {
                ?>
                <fieldset class="NoMarginBottom">
                    <legend><?php
                        echo TEXT_PRODUCTS_WITH_SUBSCRIPTIONS; ?></legend>
                    <div class="BISNPaginationWrapper"><?php echo $pagination_columns; ?></div>
                    <table class="table table-striped table-hover">
                        <thead>
                        <tr>
                            <?php
                            if ($sort_column === 'model') {
                                echo '<th>' . TABLE_HEADING_PRODUCT_MODEL . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=model'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_PRODUCT_MODEL . '">' .
                                    TABLE_HEADING_PRODUCT_MODEL . '</a></th>';
                            }

                            if ($sort_column === 'product') {
                                echo '<th>' . TABLE_HEADING_PRODUCT_NAME . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=product'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_PRODUCT_NAME . '">' .
                                    TABLE_HEADING_PRODUCT_NAME . '</a></th>';
                            }

                            if ($sort_column === 'category') {
                                echo '<th>' . TABLE_HEADING_PRODUCT_CATEGORY . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=category'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_PRODUCT_CATEGORY . '">' .
                                    TABLE_HEADING_PRODUCT_CATEGORY . '</a></th>';
                            }
                            if ($sort_column === 'subscribers') {
                                echo '<th class="center">' . TABLE_HEADING_NUM_SUBSCRIBERS . '</th>';
                            } else {
                                echo '<th class="ClickToSort center"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=subscribers'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_NUM_SUBSCRIBERS . '">' .
                                    TABLE_HEADING_NUM_SUBSCRIBERS . '</a></th>';
                            }

                            if ($sort_column === 'stock') {
                                echo '<th class="""center">' . TABLE_HEADING_CURRENT_STOCK . '</th>';
                            } else {
                                echo '<th class="ClickToSort center"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=stock'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_CURRENT_STOCK . '">' .
                                    TABLE_HEADING_CURRENT_STOCK . '</a></th>';
                            }
                            echo '<th class="center">' . TABLE_HEADING_DELETE_SUBSCRIPTIONS . '</th>';
                            echo "\n";
                            ?>
                        </tr>
                        </thead>
                        <?php

                        while (!$product_subscriptions_info->EOF) {
                            $product_null = $product_subscriptions_info->fields['products_name'] === null;
                            if ($product_null) {
                                $product_subscriptions_info->fields['products_model'] = '';
                                $product_subscriptions_info->fields['products_name'] = sprintf(TEXT_PRODUCT_ID_NOT_FOUND, (int)$product_subscriptions_info->fields['product_id']);
                                $product_subscriptions_info->fields['product_name_extra'] = '';
                                $product_subscriptions_info->fields['categories_id'] = '';
                            }
                            ?>
                            <tbody>
                            <tr class="dataTableRow">
                                <td class="dataTableContent">
                                    <?php if (!$product_null) { ?>
                                        <a href="<?php
                                    echo zen_catalog_href_link(FILENAME_PRODUCT_INFO, 'cPath=' . zen_get_product_path($product_subscriptions_info->fields['product_id']) . '&products_id=' . $product_subscriptions_info->fields['product_id']); ?>" target="_blank" title="<?php
                                    echo TEXT_TITLE_VIEW_PRODUCT; ?>"><?php
                                        echo $product_subscriptions_info->fields['products_model'] . ($product_subscriptions_info->fields['product_name_extra'] === '' ? '' : ': ' . $product_subscriptions_info->fields['product_name_extra']); ?></a>
                                    <?php } ?>
                                </td>
                                <td class="dataTableContent">
                                    <?php
                                    if (!$product_null) {
                                        echo buildLinkToProductAdminPage(
                                            $product_subscriptions_info->fields['products_name'],
                                            (int)$product_subscriptions_info->fields['product_id'],
                                            (int)$product_subscriptions_info->fields['products_type']
                                        );
                                    } else {
                                        echo $product_subscriptions_info->fields['products_name'];
                                    }
                                    ?>
                                </td>
                                <td class="dataTableContent small">
                                    <?php
                                    if (!$product_null) { ?>
                                    <a href="category_product_listing.php?cPath=<?php
                                    echo zen_get_product_path($product_subscriptions_info->fields['product_id']); ?>" title="<?php
                                    echo TEXT_TITLE_GOTO_CATEGORY; ?>"><?php
                                        echo zen_output_generated_category_path($product_subscriptions_info->fields['categories_id']); ?></a>
                                        <?php } ?>
                                </td>
                                <td class="dataTableContent center">
                                    <?php
                                    echo $product_subscriptions_info->fields['num_subscribers']; ?>
                                </td>
                                <td class="dataTableContent center">
                                    <?php
                                    echo $product_subscriptions_info->fields['current_stock']; ?>
                                </td>
                                <td class="dataTableContent center">
                                    <?php
                                    echo zen_draw_form('delete_product_subscriptions_' . $product_subscriptions_info->fields['product_id'], FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, 'option=1&action=delete');
                                    echo zen_draw_hidden_field('delete', $product_subscriptions_info->fields['product_id']);
                                    echo zen_image_submit(
                                        'button_delete.gif',
                                        TEXT_TITLE_DELETE_ALL,
                                        'onclick="return confirm(\'' .
                                        sprintf(TEXT_DELETE_SUBSCRIPTION_CONFIRM, "\\n" . $product_subscriptions_info->fields['products_model'] . ' - ' . htmlentities($product_subscriptions_info->fields['products_name'])) .
                                        '\');"'
                                    );
                                    echo '</form>';
                                    ?>
                                </td>
                            </tr>
                            </tbody>
                            <?php
                            $product_subscriptions_info->MoveNext();
                        }
                        ?>
                    </table>
                    <div class="BISNPaginationWrapper"><?php
                        echo $pagination_columns; ?></div>
                </fieldset>
                <?php
            } elseif ($option === 2) {
                ?>
                <fieldset class="NoMarginBottom">
                    <legend><?php
                        echo TEXT_ALL_SUBSCRIPTIONS; ?></legend>
                    <div class="BISNPaginationWrapper"><?php
                        echo $pagination_columns; ?></div>
                    <table>
                        <tr>
                            <?php
                            if ($sort_column === 'model') {
                                echo '<th>' . TABLE_HEADING_PRODUCT_MODEL . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=model'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_PRODUCT_MODEL . '">' .
                                    TABLE_HEADING_PRODUCT_MODEL . '</a></th>';
                            }

                            if ($sort_column === 'product') {
                                echo '<th>' . TABLE_HEADING_PRODUCT_NAME . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=product'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_PRODUCT_NAME . '">' .
                                    TABLE_HEADING_PRODUCT_NAME . '</a></th>';
                            }

                            if ($sort_column === 'date') {
                                echo '<th class="center">' . TABLE_HEADING_DATE_SUBSCRIBED . '</th>';
                            } else {
                                echo '<th class="ClickToSort center"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=date'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_DATE_SUBSCRIBED . '">' .
                                    TABLE_HEADING_DATE_SUBSCRIBED . '</a></th>';
                            }

                            if ($sort_column === 'customer_name') {
                                echo '<th>' . TABLE_HEADING_CUSTOMER_NAME . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=customer_name'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_CUSTOMER_NAME . '">' .
                                    TABLE_HEADING_CUSTOMER_NAME . '</a></th>';
                            }

                            if ($sort_column === 'customer_email') {
                                echo '<th>' . TABLE_HEADING_CUSTOMER_EMAIL . '</th>';
                            } else {
                                echo '<th class="ClickToSort"><a href="' .
                                    zen_href_link(
                                        FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS,
                                        zen_get_all_get_params(['sort', 'action']) . 'sort=customer_email'
                                    ) .
                                    '" title="' . TEXT_SORT_BY_CUSTOMER_EMAIL . '">' .
                                    TABLE_HEADING_CUSTOMER_EMAIL . '</a></th>';
                            }
                            // this block made html (php) to fix IDE inspection error!
//todo rework
                            if ($sort_column === 'languages_id') { ?>
                                <th class="center"><?php
                                    echo TABLE_HEADING_CUSTOMER_LANGUAGES_ID; ?></th>
                            <?php
                            } else { ?>
                                <th class="ClickToSort center">
                                    <a href="<?php
                                    echo zen_href_link(FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, zen_get_all_get_params(['sort', 'action']) . 'sort=languages_id'); ?>"
                                       title="<?php
                                       echo TEXT_SORT_BY_LANGUAGE_ID; ?>"><?php
                                        echo TABLE_HEADING_CUSTOMER_LANGUAGES_ID; ?></a>
                                </th>
                            <?php
                            } ?>
                            <th class="center"><?php
                                echo TABLE_HEADING_DELETE_SUBSCRIPTIONS; ?></th>
                        </tr>
                        <?php

                        while (!$subscriptions_info->EOF) {
                            //skip deleted products: is already highlighted on first entry into BISN admin page, should be deleted there
                            if ($subscriptions_info->fields['products_name'] === null) {
                                $subscriptions_info->MoveNext();
                            };
                            ?>
                            <tr class="dataTableRow">
                                <td class="dataTableContent">
                                    <?php
                                    //steve link to catalog product info using custom function without rewriting 
                                    //todo remove custom function ?>
                                    <a href="<?php
                                    echo mv_catalog_href_link(FILENAME_PRODUCT_INFO, 'cPath=' . zen_get_product_path($subscriptions_info->fields['product_id']) . '&products_id=' . $subscriptions_info->fields['product_id'], 'NONSSL', false); ?>" target="_blank" title="<?php
                                    echo TEXT_TITLE_VIEW_PRODUCT; ?>"><?php
                                        echo $subscriptions_info->fields['products_model'] . ($subscriptions_info->fields['product_name_extra'] === '' ? '' : ': ' . $subscriptions_info->fields['product_name_extra']); ?></a>
                                </td>
                                <td class="dataTableContent">
                                    <?php
                                    echo buildLinkToProductAdminPage(
                                        $subscriptions_info->fields['products_name'],
                                        (int)$subscriptions_info->fields['product_id'],
                                        (int)$subscriptions_info->fields['products_type']
                                    ); ?>
                                </td>
                                <td class="dataTableContent center">
                                    <?php
                                    echo
                                    zen_date_short($subscriptions_info->fields['date_subscribed']); ?>
                                </td>
                                <td class="dataTableContent">
                                    <?php
                                    if ($subscriptions_info->fields['customer_id'] !== null) {
                                        $is_customer = true;
                                        echo '<a href="' . zen_href_link(
                                                'customers.php?search=' . $subscriptions_info->fields['customers_email_address']
                                            ) . '" target="blank" title="' . TEXT_TITLE_VIEW_CUSTOMER . '">' . $subscriptions_info->fields['customers_firstname'] . ' ' . $subscriptions_info->fields['customers_lastname'] . '</a>';
                                    } else {
                                        $is_customer = false;
                                        echo $subscriptions_info->fields['name'];
                                    } ?>
                                </td>
                                <td class="dataTableContent">
                                    <?php
                                    $customer_email_address =
                                        ($subscriptions_info->fields['email_address'] ?? $subscriptions_info->fields['customers_email_address']);

                                    if ($is_customer) {
                                        $customer_email_address_link = zen_href_link(FILENAME_MAIL, 'origin=back_in_stock_notificationsp&amp;mode=NONSSL&customer=' . $customer_email_address . '&amp;cID=' . $subscriptions_info->fields['customer_id'], 'NONSSL');
                                    } else {
                                        $customer_email_address_link = 'mailto:' . $customer_email_address;
                                    } ?>
                                    <a href="<?php
                                    echo $customer_email_address_link; ?>" title="<?php
                                    echo TEXT_TITLE_SEND_EMAIL; ?>" target="_blank"><?php
                                        echo $customer_email_address; ?></a>
                                </td>
                                <td class="dataTableContent center smallText">
                                    <?php
                                    echo zen_get_language_icon($subscriptions_info->fields['languages_id']) . '<br>' .
                                        ucfirst(
                                            zen_get_language_name($subscriptions_info->fields['languages_id']) .
                                            ' (' . $subscriptions_info->fields['languages_id'] . ')'
                                        ); ?>
                                </td>
                                <td class="dataTableContent center">
                                    <?php
                                    echo zen_draw_form('delete_customer_subscription_' . $subscriptions_info->fields['id'], FILENAME_CEON_BACK_IN_STOCK_NOTIFICATIONS, 'option=2&action=delete');
                                    echo zen_draw_hidden_field('delete', $subscriptions_info->fields['id']);
                                    echo zen_image_submit('button_delete.gif', IMAGE_DELETE, 'onclick="return confirm(\'' . sprintf(TEXT_DELETE_SUBSCRIPTION_CONFIRM, "\\n" . $customer_email_address) . '\');"');
                                    echo '</form>'; ?>
                                </td>
                            </tr>
                            <?php
                            $subscriptions_info->MoveNext();
                        }
                        ?>
                    </table>
                    <div class="BISNPaginationWrapper"><?php
                        echo $pagination_columns; ?></div>
                </fieldset>
                <?php
            } elseif ($option === 3 || $option === 4) {
                ?>
                <fieldset>
                    <legend><?php
                        echo TEXT_SEND_OUTPUT_TITLE; ?></legend>
                    <?php
                    if ($option === 3) {
                        echo '<div>' . TEXT_TEST_OUTPUT . '</div>';
                    }
                    if (CEON_URI_MAPPING_ENABLED) {
                        echo '<p>' . TEXT_NOTE_URI_MAPPING . '</p>';
                    } ?>
                    <?php
                    //print_r ($send_output);
                    if (!empty($send_output)) {
                        foreach ($send_output as $key => $lang) {
                            //echo '$key='.$key.'<br />';
                            //echo '$lang='.$lang.'<br />';
                            print $lang;
                        }
                    }
                    ?>
                </fieldset>
                <?php
            }
            ?>
        </fieldset>
    </div>
    <div id="ceon-footer"><p><a href="https://ceon.net" target="_blank"><img src="<?php
                echo DIR_WS_IMAGES; ?>ceon-button-logo.png" alt="Ceon" id="ceon-button-logo"/></a>Module &copy; Copyright 2004-2012
            <?php
            //echo (date('Y') > 2012 ? date('Y') : 2012); ?> <!--<a href="https://dev.ceon.net/web" target="_blank">Ceon</a></p>-->
        <p id="version-info">Module Version: <?php
            echo CEON_BACK_IN_STOCK_NOTIFICATIONS_VERSION; ?></p>
        <?php
        /*?><p id="check-for-updates"><a href="https://dev.ceon.net/web/zen-cart/back-in-stock-notifications/version-checker/<?php echo CEON_BACK_IN_STOCK_NOTIFICATIONS_VERSION; ?>" target="_blank">Check for Updates</a></p><?php */ ?>
    </div>
</div>
<!-- body_eof //-->

<!-- footer //-->
<div class="footer-area">
    <?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
</div>
<!-- footer_eof //-->
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
