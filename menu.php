<?php
require_once './database.php';
require_once './user.php';
require_once './telegram_api.php';

// INLINE ACTIONS:
defined('INLINE_ACTION_VERIFY_ACCOUNT') or define('INLINE_ACTION_VERIFY_ACCOUNT', 1);
defined('INLINE_ACTION_REPLY_USER') or define('INLINE_ACTION_REPLY_USER', 2);
defined('INLINE_ACTION_SHOW_MESSAGE') or define('INLINE_ACTION_SHOW_MESSAGE', 3);
defined('INLINE_ACTION_REMOVE_ADMIN') or define('INLINE_ACTION_REMOVE_ADMIN', 4);

defined('MAX_COLUMN_LENGTH') or define('MAX_COLUMN_LENGTH', 30);
// UI constants
defined('CMD_STATISTICS') or define('CMD_STATISTICS', 'آمار ربات');
defined('CMD_ADD_ADMIN') or define('CMD_ADD_ADMIN', 'افزودن ادمین');
defined('CMD_REMOVE_ADMIN') or define('CMD_REMOVE_ADMIN', 'حذف ادمین');

defined('CMD_SUBMIT_NOTICE') or define('CMD_SUBMIT_NOTICE', 'ثبت آگهی 📖');
defined('CMD_SUPPORT') or define('CMD_SUPPORT', 'پشتیبانی 💬');

defined('CMD_MAIN_MENU') or define('CMD_MAIN_MENU', 'بازگشت به منو ↪️');

defined('CMD_GOD_ACCESS') or define('CMD_GOD_ACCESS', '/godAccess');

function alignButtons($items, $action_value, $callback_value_column, $title_column = null): array
{
    $buttons = array(array()); // an inline keyboard
    $current_row = 0;
    $column_length = 0;
    foreach($items as $item) {
        array_unshift($buttons[$current_row], array(
            TEXT_TAG => $item[$title_column] ?? $item[$callback_value_column],
            CALLBACK_DATA => wrapInlineButtonData($action_value,
                                $callback_value_column, $item[$callback_value_column] ?? 0
                            )
        ));
        // buttons callback_data is as: type/id, type determines whether it's a course or a teacher;
        $column_length += strlen($item[$related_column]);
        if($column_length > MAX_COLUMN_LENGTH) {
            $column_length = 0;
            $current_row++;
            $buttons[] = array();
        }
    }
    return $buttons;
}

function createMenu($table_name, $menu_action, $filter_query = null, $filter_index = null): ?array
{
    $query = 'SELECT * FROM ' . $table_name;
    if($filter_query && !$filter_index) // this condition just happens for remove admin menu
        $query .= ' WHERE ' . $filter_query;
    $items = Database::getInstance()->query($query);

    $options = alignButtons($items, DB_ITEM_NAME, $data_prefix);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function createUserList(string $filter_query, string $filter_index = DB_ITEM_ID): ?array
{
    $fields = implode(',', [DB_ITEM_ID, DB_ITEM_NAME, DB_USER_USERNAME]);
    $items = Database::getInstance()->query("SELECT $fields FROM " . DB_TABLE_USERS . " WHERE $filter_query ORDER BY " . DB_ITEM_NAME);
    $options = alignButtons($items, DB_ITEM_NAME, DB_TABLE_USERS . RELATED_DATA_SEPARATOR, $filter_index, DB_USER_USERNAME);
    return $options ? array(INLINE_KEYBOARD => $options) : null;
}

function getMainMenu($user_mode): array
{
    $keyboard = array('resize_keyboard' => true, 'one_time_keyboard' => true,
            'keyboard' => $user_mode == NORMAL_USER
                ? array(array(CMD_SUPPORT, CMD_SUBMIT_NOTICE))
                : array(array(CMD_STATISTICS, CMD_SUBMIT_NOTICE)));
    if($user_mode == GOD_USER)
        $keyboard['keyboard'][] = array(CMD_REMOVE_ADMIN, CMD_ADD_ADMIN);
    return $keyboard;
}

function backToMainMenuKeyboard(): array
{
    return array('resize_keyboard' => true, 'one_time_keyboard' => true,
        'keyboard' => array(
            array(CMD_MAIN_MENU)
        )
    );
}
