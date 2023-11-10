<?php
require_once './telegram_api.php';
require_once './database.php';
require_once './user.php';
require_once  './menu.php';


function isGodEnough(): bool
{
    // just trying to be funny:|
    return count(
            Database::getInstance()->query(
                'SELECT * FROM ' . DB_TABLE_USERS . ' WHERE ' . DB_USER_MODE . '=' . GOD_USER
        )) >= MAX_GODS;
}

function handleGospel(&$user, $whisper): ?string
{
    // handle god login requests
    $answer = null;
    switch($user[DB_USER_ACTION]) {
        case ACTION_WHISPER_GODS_NAME:
            if($whisper === GOD_NAME) {
                $answer = 'God\'s Secret:';
                if(!updateAction($user[DB_USER_ID], ACTION_WHISPER_GODS_SECRET)) {
                    $answer = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                    resetAction($user[DB_USER_ID]);
                }
            }
            break;
        case ACTION_WHISPER_GODS_SECRET:
            if($whisper === GOD_SECRET && !isGodEnough()) {
                if(!updateUserMode($user[DB_USER_ID], GOD_USER))
                    $answer = 'خطایی حین ثبت اطلاعات پیش اومد. دوباره تلاش کن!';
                $user[DB_USER_MODE] = GOD_USER; // update the old user object
                resetAction($user[DB_USER_ID]);
                $answer = 'Now you\'re God Almighty :)!';
            }
            break;
    }
    return $answer;
}

function handleCasualMessage(&$update) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];

    $user = getUser($user_id);

    $message = $update['message'];
    $message_id = $update['message']['message_id'];


    $data = $message[TEXT_TAG] ?? null;
    $response = handleGospel($user, $data);
    $keyboard = getMainMenu($user[DB_USER_MODE]);

    if(!$response) {
        switch($data) {
            case '/start':
                $response = 'خب! چه کاری میتونم برات انجام بدم؟';
                resetAction($user_id);
                break;
            case '/cancel':
                resetAction($user_id);
                $response = 'لغو شد!';
                break;
            case CMD_GOD_ACCESS:
                if(!isGodEnough()) {
                    $response = 'God\'s Name:';
                    if(!updateAction($user_id, ACTION_WHISPER_GODS_NAME)) {
                        $response = 'خطای غیرمنتظره پیش اومد! دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                }
                break;
            case CMD_MAIN_MENU:
                // TODO: write sth?
                $response = 'خب! چی بکنیم؟';
                resetAction($user_id);
                break;
            default:
                $response = null;
                break;
        }
    }

    if(!$response) {
        switch($user[DB_USER_MODE]) {
            case NORMAL_USER:
                if($user[DB_USER_ACTION] != ACTION_WRITE_MESSAGE_TO_ADMIN) {
                    switch($data) {
                        case CMD_SUPPORT:
                            $response = 'متن خود را در قالب یک پیام ارسال کنید.📝';
                            $keyboard = backToMainMenuKeyboard();
                            if(!updateAction($user_id, ACTION_WRITE_MESSAGE_TO_ADMIN)) {
                                $response = 'حین ورود به حالت ارسال پیام مشکلی پیش اومد. لطفا دوباره تلاش کن!';
                                resetAction($user_id);
                            }
                            break;
                        default:
                            $response = 'دستور مورد نظر صحیح نیست!';
                            break;
                    }
                } else {
                    saveMessage($user_id, $message_id);
                    foreach(getSuperiors() as $target) {
                        callMethod(
                            METH_FORWARD_MESSAGE,
                            CHAT_ID, $target[DB_USER_ID],
                            'from_chat_id', $chat_id,
                            'message_id', $message_id
                        );
                        callMethod(METH_SEND_MESSAGE,
                            CHAT_ID, $target[DB_USER_ID],
                            TEXT_TAG, 'برای پاسخ به پیام بالا میتونی از گزینه زیر استفاده کنی',
                            KEYBOARD, array(
                                INLINE_KEYBOARD => array(
                                    array(array(TEXT_TAG => 'پاسخ', CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_REPLY_USER,
                                        'message_id', $message_id
                                    )))
                                )
                            )
                        );
                    }
                    $response = "پیام شما با موفقیت ارسال شد✅ \n در صورت لزوم، تیم پشتیبانی پاسخ را از طریق همین بات به شما اعلام خواهد کرد.";
                    resetAction($user_id);

                }
                break;
            case GOD_USER:
                if($data === CMD_ADD_ADMIN) {
                    $response = 'یک پیام از اکانت موردنظرت فوروارد کن:';
                    if(!updateAction($user_id, ACTION_ADD_ADMIN)) {
                        $response = 'مشکلی حین ورود به حالت اضافه کردن ادمین پیش اومده. لطفا دوباره تلاش کن!';
                        resetAction($user_id);
                    }
                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ADD_ADMIN) {
                    if(isset($message['forward_from'])) {

                        $target_id = $message['forward_from']['id'];
                        if(!updateUserMode($target_id, ADMIN_USER)) {
                            $response = 'متاسفانه مشکلی حین ثبت اکانت بعنوان ادمین پیش اومده. لطفا دوباره تلاش کن!';
                            resetAction($user_id);
                        } else {
                            $response = 'اکانت موردنظر بعنوان ادمین ثبت شد!';
                            // notify the target user
                            callMethod(METH_SEND_MESSAGE,
                                CHAT_ID, $target_id,
                                TEXT_TAG, 'تبریک! اکانتت به دسترسی ادمین ارتقا پیدا کرد.',
                                KEYBOARD, getMainMenu(ADMIN_USER)
                            );
                            if(!updateAction($user_id, ACTION_ASSIGN_USER_NAME) || !updateActionCache($user_id, $target_id)) {
                                $response .= ' اما حین ورود به حالت تعیین اسم مشکلی پیش اومد!';
                                resetAction($user_id);
                            } else {
                                $response .= ' حالا یک اسم براش تعیین کن:';
                            }
                        }
                    } else {
                        $response = 'اکانت موردنظر حالت مخفی رو فعال کرده. برای ارتقا یافتن به ادمین باید موقتا این حالت رو غیرفعال کنه!';
                        resetAction($user_id);
                    }

                    break;
                } else if($user[DB_USER_ACTION] == ACTION_ASSIGN_USER_NAME) {
                    // set message text as the name for the admin
                    // cache is the target user id
                    $response = assignUserName($user[DB_USER_ACTION_CACHE], $data) ? 'اسم این کاربر با موفقیت ثبت شد.'
                        : 'مشکلی در ثبت اسم این کاربر پیش اومد!';
                    resetAction($user_id);
                    break;
                } else if($data === CMD_REMOVE_ADMIN) {
                    $response = 'روی شخص موردنظرت کلیک کن تا از حالت ادمین خارج بشه:';
                    $keyboard = createMenu(DB_TABLE_USERS, INLINE_ACTION_REMOVE_ADMIN, DB_USER_MODE . '=' . ADMIN_USER);
                    break;
                }
            case ADMIN_USER:
                // get admin's action value
                if(!$user[DB_USER_ACTION]) {
                    // if action value is none
                    switch($data) {
                        case CMD_STATISTICS:
                            $response = "آماره ربات:" . "\n";
                            foreach(getStatistics() as $field=>$stat) {
                                $response .= "{$stat['fa']}: {$stat['total']} \n";
                            }
                            break;
                        default:
                            $response = 'دستور مورد نظر صحیح نیست!';
                            break;
                    }
                }
                else {
                    switch($user[DB_USER_ACTION]) {
                        /*case ACTION_SENDING_BOOKLET_FILE:
                            $file = getFileFrom($message);
                            if(!$file) $response = 'هیچ فایلی ارسال نشده. دوباره ارسال کن!';
                            else {
                                $result = addBooklet($user, $file);
                                if(isset($result['err'])) $response = $result['err'];
                                else {
                                    if(!updateAction($user_id, ACTION_SET_BOOKLET_CAPTION) || !updateActionCache($user_id, $result['id']))
                                        $response = 'جزوه ثبت شد ولی مشکلی حین ورود به حالت تعیین کپشن پیش اومد!';
                                    else {
                                        $response = 'جزوه مورد نظر با موفقیت ارسال شد. حالا کپشن جزوه رو مشخص کن:';
                                        $keyboard = array(
                                            INLINE_KEYBOARD => array(
                                                array(
                                                    //columns:
                                                    array(TEXT_TAG => 'کپشن فایل', CALLBACK_DATA => 0),
                                                    array(TEXT_TAG => 'وارد کردن کپشن', CALLBACK_DATA => 1)
                                                )
                                            )
                                        );
                                    }

                                }
                            }
                            break;*/
                        case ACTION_WRITE_REPLY_TO_USER:
                            $msg = getMessage($user[DB_USER_ACTION_CACHE]);
                            if($msg) {
                                callMethod(METH_SEND_MESSAGE,
                                    CHAT_ID, $msg[DB_MESSAGES_SENDER_ID],
                                    TEXT_TAG, 'ادمین پیام شما را پاسخ داد.',
                                    'reply_to_message_id', $msg[DB_ITEM_ID],
                                     KEYBOARD, array(
                                        INLINE_KEYBOARD => array(
                                            array(
                                                array(TEXT_TAG => 'مشاهده',
                                                    CALLBACK_DATA => wrapInlineButtonData(INLINE_ACTION_SHOW_MESSAGE,
                                                        'rid', $message_id,
                                                        'by', $chat_id,
                                                        'to', (int)$msg[DB_ITEM_ID]
                                                    )
                                                )
                                            )
                                        )
                                     )
                                );
                                markMessageAsAnswered($user[DB_USER_ACTION_CACHE]);
                                $response = 'پاسخ شما با موفقیت ارسال شد.';
                            } else {
                                $response = 'چنین پیامی اصلا وجود نداره که بخوای جوابش رو بدی!';
                            }
                            resetAction($user_id);
                            break;
                        default:
                            $response = 'عملیات موردنظر تعریف نشده است!';
                            resetAction($user_id);
                            break;
                    }
                }
                break;
        }
    }

    callMethod(
        METH_SEND_MESSAGE,
        CHAT_ID, $chat_id,
        TEXT_TAG, $response,
        'reply_to_message_id', $message_id,
        KEYBOARD, $keyboard

    );
}

function handleCallbackQuery(&$update) {
    $callback_id = $update[CALLBACK_QUERY]['id'];
    $chat_id = $update[CALLBACK_QUERY]['message']['chat']['id'];
    $message_id = $update[CALLBACK_QUERY]['message']['message_id'];
    $user_id = $update[CALLBACK_QUERY]['from']['id'];
    $data = json_decode($update[CALLBACK_QUERY]['data'], true);
    $text = $update[CALLBACK_QUERY]['message']['text'];
    $answer = null;
    $keyboard = null;
    $user = getUser($user_id);
    switch($data['act']) {
        case INLINE_ACTION_VERIFY_ACCOUNT:
            // check membership is ok
            // because if it wasn't ok, this function couldn't be called
            $answer = 'مرسی که عضو کانال های ما شدی :)';
            callMethod(METH_SEND_MESSAGE,
                CHAT_ID, $chat_id,
                TEXT_TAG, 'چه کاری میتونم برات انجام بدم؟',
                KEYBOARD,  getMainMenu($user[DB_USER_MODE])
            );
            break;
        case INLINE_ACTION_REPLY_USER:
            // admin is attempting to answer a message
            updateAction($user_id, ACTION_WRITE_REPLY_TO_USER);
            updateActionCache($user_id, $data['message_id']);
            $answer = 'پاسخ خودتو بنویس: (لغو /cancel)';
            if(isMessageAnswered($data['message_id']))
                callMethod('answerCallbackQuery', 
                    'callback_query_id', $callback_id,
                    TEXT_TAG, 'این پیام قبلا پاسخ داده شده است!',
                    'show_alert', true
                );
            callMethod(
                METH_SEND_MESSAGE,
                CHAT_ID, $chat_id,
                TEXT_TAG, $answer,
                'reply_to_message_id', $message_id,
                KEYBOARD, backToMainMenuKeyboard()
            );
            exit();
        case INLINE_ACTION_SHOW_MESSAGE:
            if(isset($data['rid']) && isset($data['by'])) {
                callMethod(
                    METH_COPY_MESSAGE,
                    'message_id', $data['rid'],
                    CHAT_ID, $chat_id,
                    'from_chat_id', $data['by'],
                    'reply_to_message_id', $data['to'] ?? 0
                );
                callMethod(METH_DELETE_MESSAGE,
                    'message_id', $message_id,
                    CHAT_ID, $chat_id
                ); // remove the show message box
            } else
                $answer = 'خطای غیرمنتظره حین باز کردن پیام اتفاق افتاد!';
            break;
        case INLINE_ACTION_REMOVE_ADMIN:
            if(!updateUserMode($data[DB_ITEM_ID], NORMAL_USER)) {
                $answer = 'مشکلی حین تغییر کاربری پیش اومد. لطفا دوباره تلاش کن!';
            } else $answer = 'کاربر موردنظر به دسترسی عادی بازگشت!';
            break;

        default:
            // TODO: sth is wrong!
            $answer = 'گزینه انتخاب شده اشتباه است!';
            resetAction($user_id);
            break;
    }
    if($keyboard)
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            'message_id', $message_id,
            TEXT_TAG, $answer,
            KEYBOARD, $keyboard
        );
    else
        callMethod(METH_EDIT_MESSAGE,
            CHAT_ID, $chat_id,
            'message_id', $message_id,
            TEXT_TAG, $answer
        );
}
