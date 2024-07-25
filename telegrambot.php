<?php
$botToken = '';
$apiURL = "https://api.telegram.org/bot$botToken/";
$updateData = file_get_contents('php://input');
$update = json_decode($updateData, true);
// file_put_contents('test.json', json_encode($update)); for debugging 
{ //all the Varibles 

    if (isset($update['callback_query'])) {
        $callbackQueryId = $update["callback_query"]["id"] ?? '';
        $callbackData = $update['callback_query']['data'] ?? '';
        $message_id = $update['callback_query']['message']['message_id'] ?? '';
        $chatid = $update['callback_query']['from']['id'] ?? '';
    } else {
        $message = $update['message']['text'] ?? '';
        $chatid = $update['message']['from']['id'] ?? '';
        $firstname = $update['message']['from']['first_name'] ?? '';
        $message_id = $update['message']['message_id'] ?? '';
        $language_code = $update['message']['from']['language_code'] ?? '';

        // Process entities for formatting
        if (isset($update['message']['entities'])) {
            $entities = $update['message']['entities'];

            // Make sure 'message' and 'entities' are set before processing
            if (isset($message) && isset($entities)) {
                // Convert to UTF-8 encoding if not already
                $message = utf8ize($message);
                // Process entities if available
                $message = processEntities($message, $entities);
            }
        }
    }
    define('SESSION_FILE', 'session.json');
} { // Database connection configuration

    $host = '';
    $username = '';
    $password = '';
    $dbname = '';

    // Connect to the database
    $mysqli = new mysqli($host, $username, $password, $dbname);
    $mysqli->set_charset("utf8mb4");

    // Check the connection
    if ($mysqli->connect_errno) {
        echo 'Failed to connect to MySQL: ' . $mysqli->connect_error;
        exit();
    }
} { //all the function in telegram bot
    function sendMessage($chatID, $message, $message_id = null, $reply_markup = null, $parsemode = null)
    {
        global $apiURL;

        $data = [
            'chat_id' => $chatID,
            'text' => $message,
            'reply_to_message_id' => $message_id,
        ];
        if ($message_id !== null) {
            $data['reply_to_message_id'] = $message_id;
        }
        if ($reply_markup !== null) {
            $data['reply_markup'] = $reply_markup;
        }
        if ($parsemode !== null) {
            $data['parse_mode'] = $parsemode;
        }

        $url = $apiURL . 'sendMessage?' . http_build_query($data);

        file_get_contents($url);
    }

    function deleteMessage($chatID, $messageID)
    {
        global $apiURL;

        $data = [
            'chat_id' => $chatID,
            'message_id' => $messageID,
        ];

        $url = $apiURL . 'deleteMessage?' . http_build_query($data);
        file_get_contents($url);
    }
    function editMessageText($chatid, $message_id, $text, $reply_markup = null)
    {
        global $apiURL;

        $data = [
            'chat_id' => $chatid,
            'message_id' => $message_id,
            'text' => $text,
        ];
        if ($reply_markup !== null) {
            $data['reply_markup'] = $reply_markup;
        }

        $url = $apiURL . 'editMessageText?' . http_build_query($data);

        file_get_contents($url);
    }
    function sendLocation($chatid, $messageID, $latitude, $longitude, $address, $reply_markup = null)
    {
        global $apiURL;

        $data = [
            'chat_id' => $chatid,
            'message_id' => $messageID,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
        ];

        if ($reply_markup !== null) {
            $data['reply_markup'] = $reply_markup;
        }

        $url = $apiURL . 'sendVenue?' . http_build_query($data);
        file_get_contents($url);
    }
    function sendnotification($callbackQueryId, $text, $alert)
    {
        global $apiURL;

        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $alert, //True = show an alert , false = show a small notification 
        ];
        $url = $apiURL . 'answerCallbackQuery?' . http_build_query($data);
        file_get_contents($url);
    }
    function userExists($chatid) //check user in database
    {
        global $mysqli;
        $chatid = mysqli_real_escape_string($mysqli, $chatid);
        // $query = "SELECT chat_id FROM bot_users WHERE chat_id = '$chatid'";
        $query = "SELECT T_ID FROM users WHERE T_ID = '$chatid'";
        $result = mysqli_query($mysqli, $query);
        return mysqli_num_rows($result) > 0;
    }
    function insertUser($chatid, $firstname)
    {
        global $mysqli;
        $chatid = mysqli_real_escape_string($mysqli, $chatid);
        $firstname = mysqli_real_escape_string($mysqli, $firstname);
        $joined = date("Y-m-d H:i:s");
        $query = "INSERT INTO `users` (`T_ID`, `T_FullName`, `Date`) VALUES ('$chatid', '$firstname', '$joined')";

        if ($mysqli->query($query)) {
            echo 'Message saved successfully!';
        } else {
            echo 'Failed to save the message. Error: ' . $mysqli->error;
        }
        $mysqli->close();
    }
    function isadmin($chatid)
    {
        global $mysqli;
        $query = "SELECT `is_admin` FROM `users` WHERE `T_ID` = $chatid";
        $result = $mysqli->query($query);
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $isAdmin = $row["is_admin"];
        } else {
            $isAdmin = false;
        }
        return $isAdmin;
    }
    function convertPersianToEnglishNumerals($input)
    {
        $persianNumerals = ["۰", "۱", "۲", "۳", "۴", "۵", "۶", "۷", "۸", "۹"];
        $englishNumerals = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"];
        return str_replace($persianNumerals, $englishNumerals, $input);
    }
    function validateNationalID($nationalID)
    {

        $digits = str_split($nationalID);
        $checksum = array_pop($digits);

        // Calculate the checksum
        $checksumCalculated = 0;
        for ($i = 0; $i < 9; $i++) {
            $checksumCalculated += $digits[$i] * (10 - $i);
        }

        $checksumCalculated %= 11;
        if ($checksumCalculated < 2) {
            $checksumCalculated = 0;
        } else {
            $checksumCalculated = 11 - $checksumCalculated;
        }

        return $checksum == $checksumCalculated;
    }
    function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, "UTF-8", mb_detect_encoding($mixed, "UTF-8, ISO-8859-1"));
        }
        return $mixed;
    }
    function mb_substr_replace($string, $replacement, $start, $length, $encoding = 'UTF-8')
    {
        $prefix = mb_substr($string, 0, $start, $encoding);
        $suffix = mb_substr($string, $start + $length, null, $encoding);
        return $prefix . $replacement . $suffix;
    }
    function escapeSpecialCharacters($text)
    {
        $charactersToEscape = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        foreach ($charactersToEscape as $char) {
            $text = str_replace($char, "\\" . $char, $text);
        }

        return $text;
    }
    function processEntities($message, $entities)
    {
        usort($entities, function ($a, $b) {
            return $b['offset'] - $a['offset'];
        });

        $formattedMessage = escapeSpecialCharacters($message);
        $tagStack = [];

        foreach ($entities as $entity) {
            $offset = $entity['offset'];
            $length = $entity['length'];
            $type = $entity['type'];

            if ($offset < 0 || $offset >= mb_strlen($message, 'UTF-8') || $length <= 0 || ($offset + $length) > mb_strlen($message, 'UTF-8')) {
                continue; // Invalid entity, skip it
            }

            $startTag = '';
            $endTag = '';

            switch ($type) {
                case 'bold':
                    $startTag = '*';
                    $endTag = '*';
                    break;
                case 'italic':
                    $startTag = '_';
                    $endTag = '_';
                    break;
                case 'underline':
                    $startTag = '__';
                    $endTag = '__';
                    break;
                case 'strikethrough':
                    $startTag = '~';
                    $endTag = '~';
                    break;
                case 'spoiler':
                    $startTag = '||';
                    $endTag = '||';
                    break;
                case 'code':
                    $startTag = '`';
                    $endTag = '`';
                    break;
                case 'text_link':
                    $startTag = '[';
                    $endTag = '](' . $entity['url'] . ')';
                    break;
            }

            if ($startTag !== '' && $endTag !== '') {
                $tagStack[] = ['start' => $startTag, 'end' => $endTag, 'offset' => $offset, 'length' => $length];
            }
        }

        // Sort the tags by their offset in descending order to handle nested tags correctly
        usort($tagStack, function ($a, $b) {
            return $b['offset'] - $a['offset'];
        });

        // Apply the tags
        foreach ($tagStack as $tag) {
            $startTag = $tag['start'];
            $endTag = $tag['end'];
            $offset = $tag['offset'];
            $length = $tag['length'];

            $formattedMessage = mb_substr_replace($formattedMessage, $endTag, $offset + $length, 0, 'UTF-8');
            $formattedMessage = mb_substr_replace($formattedMessage, $startTag, $offset, 0, 'UTF-8');
        }

        return $formattedMessage;
    }
    function broadcast($message, $admin)
    {
        global $mysqli;
        if ($admin == true) {
            $query = "SELECT `T_ID` FROM `users` WHERE `is_admin` = 1";
            $result = mysqli_query($mysqli, $query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $chat_id = $row["T_ID"];
                    sendMessage($chat_id, $message, null, null, "MarkdownV2");
                }
            } else {
                echo "No users found for the broadcast.";
            }
        } else {
            $query = "SELECT `T_ID` FROM `users` WHERE 1";
            $result = mysqli_query($mysqli, $query);
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $chat_id = $row["T_ID"];
                    sendMessage($chat_id, $message, null, null, "MarkdownV2");
                }
            } else {
                echo "No users found for the broadcast.";
            }
        }
    }
    function insertBroadcast($chatid, $message, $admin)
    {
        global $mysqli;
        $chatid = mysqli_real_escape_string($mysqli, $chatid);
        $message = mysqli_real_escape_string($mysqli, $message);
        $time = date("Y-m-d H:i:s");
        $query = "INSERT INTO `broadcast_message` ( `chat_id`, `message`, `only_admin`, `time`) VALUES ('$chatid', '$message', '$admin', '$time')";

        if ($mysqli->query($query)) {
            echo 'Message saved successfully!';
        } else {
            echo 'Failed to save the message. Error: ' . $mysqli->error;
        }
        $mysqli->close();
    }
    // Function to check if any configs are available
    function CheckConfigs()
    {
        global $mysqli;
        $query = "SELECT COUNT(*) as count FROM `Configs` WHERE `Receiver_ID` IS NULL";
        $result = mysqli_query($mysqli, $query);
        $row = $result->fetch_assoc();
        return $row['count'] > 0;
    }

    // Function to select a free config
    function SelectConfig()
    {
        global $mysqli;

        // Check if the database connection is successful
        if (!$mysqli) {
            error_log("Database connection failed: " . mysqli_connect_error());
            return "Database connection error";
        }

        $query = "SELECT Config_Link FROM Configs WHERE Receiver_ID IS NULL LIMIT 1";
        $result = mysqli_query($mysqli, $query);

        // Check if the query executed successfully
        if (!$result) {
            error_log("Query failed: " . mysqli_error($mysqli));
            return "Query execution error";
        }

        // Check if any rows were returned
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['Config_Link'];
        }

        // If no rows were found
        return "Unknown";
    }


    // Function to insert new configs into the database
    function InsertConfigs($config_link)
    {
        global $mysqli;
        $query = "INSERT INTO `Configs` (`Config_Link`) VALUES ('" . $config_link . "')";
        return mysqli_query($mysqli, $query);
    }

    // Function to get the receiver ID from the user chat ID
    function GetReceiver_ID($chatid)
    {
        global $mysqli;
        $query = "SELECT `ID` FROM `users` WHERE `T_ID` = " . $chatid;
        $result = mysqli_query($mysqli, $query);
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row["ID"];
        } else {
            return null;
        }
    }

    // Function to update the config with the user ID and date received
    function UpdateConfigs($Config_Link, $chatid)
    {
        global $mysqli;
        $Receiver_ID = GetReceiver_ID($chatid);
        $date = date("Y-m-d H:i:s");
        if ($Receiver_ID !== null) {
            $query = "UPDATE `Configs` SET `Receiver_ID` = " . $Receiver_ID . ", `Date_Received` = '" . $date . "' WHERE `Config_Link` = '" . $Config_Link . "'";
            return mysqli_query($mysqli, $query);
        }
        return false;
    }

    // Function to check if the user already has a config
    function CheckUserConfig($chatid)
    {
        global $mysqli;
        $Receiver_ID = GetReceiver_ID($chatid);
        if ($Receiver_ID !== null) {
            $query = "SELECT COUNT(*) as count FROM `Configs` WHERE `Receiver_ID` = " . $Receiver_ID;
            $result = mysqli_query($mysqli, $query);
            $row = $result->fetch_assoc();
            return $row['count'] > 0;
        }
        return false;
    }

    //logic to handle uploaded file 

    function validateURL($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    function processFile($filePath, $chatid, $message_id)
    {
        global $mysqli;

        $file = fopen($filePath, "r");
        $totalLines = 0;
        $validUrls = 0;
        $invalidUrls = 0;
        $invalidLines = [];
        $startTime = microtime(true);
        $text = "شروع بررسی فایل";
        sendMessage($chatid, $text, $message_id);
        if ($file) {
            while (($line = fgets($file)) !== false) {
                $totalLines++;
                $url = trim($line);
                if (validateURL($url)) {
                    $query = "INSERT INTO `Configs` (`Config_Link`, `Added_By`) VALUES ('$url', '$chatid')";
                    if ($mysqli->query($query)) {
                        echo 'Message saved successfully!';
                    } else {
                        echo 'Failed to save the message. Error: ' . $mysqli->error;
                    }
                    // $stmt = $mysqli->prepare("INSERT INTO `Configs` (`Config_Link` , `Added_By`) VALUES (??)");
                    // $stmt->bind_param('ss', $url, $chatid);
                    // $stmt->execute();
                    // $stmt->close();
                    $validUrls++;
                } else {
                    $invalidLines[] = $totalLines;
                    $invalidUrls++;
                }

                if ($totalLines % 10 == 0) // Update progress every 10 lines
                {
                    $progressText = "در حال بررسی: " . $totalLines . "/" . ($totalLines + $invalidUrls);
                    editMessageText($chatid, $message_id, $progressText . "1");
                    // sendMessage($chatid, $progressText, $message_id);
                }
            }
            fclose($file);

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            $finalMessage = "وارد کردن کانفیگ‌های جدید به دیتابیس موفقیت‌آمیز\n";
            $finalMessage .= "تعداد کل کانفیگ‌های وارد شده به دیتابیس: $validUrls\n";
            $finalMessage .= "تعداد کانفیگ‌های دارای مشکل و وارد نشده به دیتابیس: $invalidUrls\n";
            $finalMessage .= "خطوط دارای ایراد: " . implode(', ', $invalidLines) . "\n";
            $finalMessage .= "تعداد کل کانفیگ‌ها: $totalLines\n";
            $finalMessage .= "زمان کل: " . gmdate("H:i:s", $executionTime) . "\n";

            sendMessage($chatid,  $finalMessage, $message_id);


            return true;
        }
        return false;
    }

    function getFilePath($fileId)
    {
        global $apiURL;
        $response = file_get_contents($apiURL . "getFile?file_id=" . $fileId);
        $response = json_decode($response, true);

        if ($response['ok']) {
            $file_path = $response['result']['file_path'];
            return "https://api.telegram.org/file/bot" . $GLOBALS['botToken'] . "/" . $file_path;
        }

        return false;
    }

    // Function to send a request to the Telegram API
    function apiRequest($method, $parameters)
    {
        global $apiURL;

        if (!is_string($method)) {
            error_log("Method name must be a string\n");
            return false;
        }

        if (!$parameters) {
            $parameters = array();
        } else if (!is_array($parameters)) {
            error_log("Parameters must be an array\n");
            return false;
        }

        $parameters["method"] = $method;

        $ch = curl_init($apiURL . $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($parameters));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
    // Function to download a file from Telegram
    function downloadFile($filePath, $destination)
    {
        global $botToken;
        $fileUrl = 'https://api.telegram.org/file/bot' . $botToken . '/' . $filePath;
        $fileContent = file_get_contents($fileUrl);

        file_put_contents($destination, $fileContent);
    }

    // Function to read session state from the JSON file
function readSessionState($chatid) {
    if (file_exists(SESSION_FILE)) {
        $sessions = json_decode(file_get_contents(SESSION_FILE), true);
        if (isset($sessions[$chatid])) {
            return $sessions[$chatid];
        }
    }
    return null;
}

// Function to write session state to the JSON file
function writeSessionState($chatid, $state) {
    $sessions = [];
    if (file_exists(SESSION_FILE)) {
        $sessions = json_decode(file_get_contents(SESSION_FILE), true);
    }
    $sessions[$chatid] = $state;
    file_put_contents(SESSION_FILE, json_encode($sessions));
}

// Function to delete session state from the JSON file
function deleteSessionState($chatid) {
    if (file_exists(SESSION_FILE)) {
        $sessions = json_decode(file_get_contents(SESSION_FILE), true);
        unset($sessions[$chatid]);
        file_put_contents(SESSION_FILE, json_encode($sessions));
    }
}
} { //call back query function for inline keyboard options 
    if (isset($update["callback_query"])) {
        if ($callbackData === "CreateNew") {
            if (CheckConfigs() === true) {
                if (CheckUserConfig($chatid) === false) {
                    deleteMessage($chatid, $message_id);
                    $Config_Link = SelectConfig();
                    UpdateConfigs($Config_Link, $chatid);
                    $text = "کانفیگ رایگان برای شما ایجاد شد!";
                    sendnotification($callbackQueryId, $text, true);
                    $message = "کانفیگ اهدایی شما از طریق لینک زیر میتوانید دسترسی داشته باشید `" . $Config_Link . " ` ";
                    sendMessage($chatid, $message, $message_id, $replyMarkup = null, 'MarkdownV2');
                } else {
                    $text = "شما قبلا یک بار کانفیگ رایگان را دریافت کرده اید!";
                    sendnotification($callbackQueryId, $text, true);

                    $text  = "شما قبلا یکبار کانفیگ رایگان را دریافت کرده اید برای دسترسی مجدد به لینک اشتراک خود میتوانید از طریق گزینه 'مشخصات کانفیگ من' اقدام بفرمایید";
                    sendMessage($chatid, $text, $message_id);
                }
            } else {
                $text = "متاسفم \n در حال حاضر کانفیگ های ما به اتمام رسیده!";
                sendnotification($callbackQueryId, $text, true);

                $text = "متأسفم در حال حاضر تمامی کانفیگ هایی اهدا شده تمام شده \n ولی در اسرع وقت شارژ خواهد شد! \n برای اینکه اولین نفری باشید که از شارژ شدن کانفیگ ها مطلع شوید میتوانید از طریق کانال تلگرامی یا اکانت توییتر با خبر شوید!";
                sendMessage($chatid, $text, $message_id);
            }
        }
        if ($callbackData === "UploadConfigs") {
            writeSessionState($chatid, 'waiting_for_file');
            $text = "لطفا فایل با فرمت .txt را اپلود کنید ";
            $keyboard = [
                [
                    ["text" => "کنسل", "callback_data" => "CancelUpload"],
                ]
            ];
            $replyMarkup = ["inline_keyboard" => $keyboard];
            $encodedMarkup = json_encode($replyMarkup);
            editMessageText($chatid, $message_id, $text, $encodedMarkup);


        } elseif ($callbackData === 'CancelUpload') {
            deleteSessionState($chatid);
            deleteMessage($chatid, $message_id);
            $text = "عملیات بارگذاری لغو شد!";
            sendMessage($chatid, $text);
        }
    }
} { // Main Menu Keybaord 
    function generateMainMenuKeyboard($chatid)
    {
        $keyboard = [
            [
                ['text' => 'دریافت کانفیگ رایگان'],
                ['text' => 'مشخصات کانفیگ من'],
            ]
        ];
        $isAdmin = isadmin($chatid);
        if ($isAdmin) {
            $keyboard[] = [['text' => 'منو ادمین']];
        }

        $reply_markup = json_encode([
            'keyboard' => $keyboard,
            'is_persistent' => false,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
            'selective' => true
        ]);

        return $reply_markup;
    }
}
switch ($message) {
    case "/start":
        $text = "سلام " . $firstname . " \n به ربات کوین خوش آمدید برای استفاده از امکانات ربات میتوانید از گزینه های زیر استفاده کنید :";
        $reply_markup = generateMainMenuKeyboard($chatid);
        sendMessage($chatid, $text, $message_id, $reply_markup);
        if (!userExists($chatid)) {
            insertUser($chatid, $firstname);
        }
        break;
    case "دریافت کانفیگ رایگان":
        $text = "برای دریافت کانفیگ رایگان خود میتوانید از طریق گزینه زیر اقدام بفرمایید :";
        $keyboard = [
            [
                ["text" => "ایجاد کانفیگ جدید", "callback_data" => "CreateNew"],
            ]
        ];
        $replyMarkup = ["inline_keyboard" => $keyboard];
        $encodedMarkup = json_encode($replyMarkup);
        sendMessage($chatid, $text, $message_id, $encodedMarkup);


        if (!userExists($chatid)) {
            insertUser($chatid, $firstname);
        }
        break;
    case "مشخصات کانفیگ من":
        $text = "در حال حاضر این بخش به اتمام نرسیده است !!";
        $reply_markup = generateMainMenuKeyboard($chatid);
        sendMessage($chatid, $text, $message_id, $reply_markup);
        if (!userExists($chatid)) {
            insertUser($chatid, $firstname);
        }
        break;

    case 'منو ادمین':
        $isadmin = isadmin($chatid);
        if ($isadmin == true) {
            $keyboard =
                [
                    [
                        ['text' => 'ارسال پیام همگانی'],
                        // ['text' => 'تبدیل کاربر به ادمین'],
                        ['text' => 'ارسال فایل کانفیگ ها']
                    ],
                    [
                        ['text' => ' بازگشت به منوی اصلی 🔙']
                    ]
                ];
            $reply_markup =
                json_encode(
                    [
                        'keyboard' => $keyboard,
                        'is_persistent' => false,
                        'resize_keyboard' => true,
                        'one_time_keyboard' => false,
                        'selective' => true
                    ]
                );
            $message = 'به پنل ادمین خوش آمدید!';
            sendMessage($chatid, $message, $message_id, $reply_markup);
        } else {
            $message = 'این منو فقط برای کاربران ادمین میباشد!';
            sendMessage($chatid, $message, $message_id);
        }
        break;
    case 'بازگشت به منوی اصلی 🔙':
        $isadmin = isadmin($chatid);
        if ($isadmin == true) {
            $text = "🏠";
            $reply_markup = generateMainMenuKeyboard($chatid);
            sendMessage($chatid, $text, $message_id, $reply_markup);
        } else {
            $message = 'این منو فقط برای کاربران ادمین میباشد!';
            sendMessage($chatid, $message, $message_id);
        }
        break;

    case 'ارسال فایل کانفیگ ها':
        $isadmin = isAdmin($chatid);
        if ($isadmin) {
            $text = "برای دریافت کانفیگ رایگان خود میتوانید از طریق گزینه زیر اقدام بفرمایید :";
            $keyboard = [
                [
                    ["text" => "اپلود فایل کانفیگ", "callback_data" => "UploadConfigs"],
                ]
            ];
            $replyMarkup = ["inline_keyboard" => $keyboard];
            $encodedMarkup = json_encode($replyMarkup);
            sendMessage($chatid, $text, $message_id, $encodedMarkup);
        } else {
            $text = "شما دسترسی به این بخش را ندارید !";
            sendMessage($chatid, $text, $message_id);
        }
        break;
} { //create a JSON-Format file for Broadcast Message and broadcast message  
    if (file_exists($chatid . 'broadcast.json')) {
        $broadcast_data = json_decode(file_get_contents($chatid . 'broadcast.json'), true);
    } else {
        $broadcast_data = [
            'action_step' => null, // Initialize action_step
            'message' => null,
            'only_admin' => null,
        ];
    }

    if ($message == "ارسال پیام همگانی" && is_null($broadcast_data['action_step'])) {
        $isadmin = isadmin($chatid);
        if ($isadmin == true) {
            $broadcast_data = [
                'action_step' => 1,
                'message' => null,
                'only_admin' => null,
            ];
            file_put_contents($chatid . 'broadcast.json', json_encode($broadcast_data));

            $text = "لطفا پیام مد نظر خود را ارسال کنید:";
            sendMessage($chatid, $text, $message_id);
        } else {
            $message = 'این منو فقط برای کاربران ادمین میباشد!';
            sendMessage($chatid, $message, $message_id);
        }
    } elseif ($broadcast_data['action_step'] == 1 && is_null($broadcast_data['message'])) {
        $broadcast_data['action_step'] = 2;
        $broadcast_data['message'] = $message;
        $text = "میخواهید فقط برای ادمین ها ارسال شود؟";
        $keyboard =
            [
                [
                    ["text" => "بله", "callback_data" => "admin_yes"],
                    ["text" => "خیر", "callback_data" => "admin_no"]
                ],
                [["text" => "کنسل", "callback_data" => "cancel_broadcast"]],

            ];
        $replyMarkup = ["inline_keyboard" => $keyboard];
        $encodedMarkup = json_encode($replyMarkup);
        sendMessage($chatid, $text, $message_id, $encodedMarkup);



        // Save the updated user_data to the JSON file
        // When saving the data to the JSON file, use the JSON_UNESCAPED_UNICODE option
        file_put_contents($chatid . 'broadcast.json', json_encode($broadcast_data, JSON_UNESCAPED_UNICODE));
    } elseif ($broadcast_data['action_step'] == 2) {
        // Check if the user canceled the process
        if ($callbackData === "cancel_broadcast") {
            deleteMessage($chatid, $message_id);
            unlink($chatid . 'broadcast.json'); // Remove the JSON file
            $text = "ارسال پیام همگانی توسط شما لغو شد!";
            sendMessage($chatid, $text, null);
        } elseif ($callbackData === "admin_yes") {
            $broadcast_data['only_admin'] = true;
            $message = "لطفا اطلاعات زیر را قبل از ارسال تأیید کنید: \n پیام شما : " . $broadcast_data['message'] . "\n این پیام فقط برای ادمین ها ارسال میشود.";
            $keyboard = [
                [
                    ["text" => "تایید", "callback_data" => "confirm_broadcast"],
                    ["text" => "کنسل", "callback_data" => "cancel_broadcast"],
                ]
            ];
            $replyMarkup = ["inline_keyboard" => $keyboard];
            $encodedMarkup = json_encode($replyMarkup);
            editMessageText($chatid, $message_id, $message, $encodedMarkup);
            file_put_contents($chatid . 'broadcast.json', json_encode($broadcast_data));
        } elseif ($callbackData === "admin_no") {
            $broadcast_data['only_admin'] = false;
            $message = "لطفا اطلاعات زیر را قبل از ارسال تأیید کنید: \n پیام شما : " . $broadcast_data['message'] . "\n این پیام برای همه افراد ارسال خواهد شد و هیچگونه گزینه بازگشتی نخواهد داشت!";
            $keyboard = [
                [
                    ["text" => "تایید", "callback_data" => "confirm_broadcast"],
                    ["text" => "کنسل", "callback_data" => "cancel_broadcast"],
                ]
            ];
            $replyMarkup = ["inline_keyboard" => $keyboard];
            $encodedMarkup = json_encode($replyMarkup);
            editMessageText($chatid, $message_id, $message, $encodedMarkup);
            $text = 'توجه داشته باشید که این پیام برای همه افرادی که از ربات استفاده میکنن ارسال خواهد شد و هیچ راه بازگشتی درصورت اشتباه بودن متن پیام نخواهد بود!';
            sendnotification($callbackQueryId, $text, true);
            file_put_contents($chatid . 'broadcast.json', json_encode($broadcast_data));
        } elseif ($callbackData === "confirm_broadcast") {
            $text = "پیام شما با موفقیت ارسال شد.";
            $message = $broadcast_data['message'];
            $admin = $broadcast_data['only_admin'];
            deleteMessage($chatid, $message_id);
            sendMessage($chatid, $text);
            broadcast($message, $admin);
            insertBroadcast($chatid, $message, $admin);
            unlink($chatid . 'broadcast.json');
        }
    }
} 
{// Upload File Process
    $state = readSessionState($chatid);
if ($state === 'waiting_for_file') {
    // Handle the incoming message or file
    if (isset($update['message'])) {
        if (isset($update['message']['document'])) {
            // Handle file upload
            $fileId = $update['message']['document']['file_id'];
            $fileName = $update['message']['document']['file_name'];
            $isadmin = isadmin($chatid);
            if (!$isadmin) {
                $message = "متاسفم متوجه نشدم لطفا از طریق منو زیر اقدام بفرمایید";
                sendMessage($chatid, $message, $messageId);
                exit();
            }

            if (pathinfo($fileName, PATHINFO_EXTENSION) !== 'txt') {
                $message = "Please upload a file with .txt format";
                sendMessage($chatid, $message , $message_id);
            } else {
                // Process the file
                $fileInfo = json_decode(file_get_contents($apiURL . 'getFile?file_id=' . $fileId), true);

                if ($fileInfo && isset($fileInfo['result']['file_path'])) {
                    $filePath = $fileInfo['result']['file_path'];
                    $fileUrl = 'https://api.telegram.org/file/bot' . $botToken . '/' . $filePath;

                    // Create a temporary file to store the contents
                    $tempFilePath = tempnam(sys_get_temp_dir(), 'uploadedFile');
                    file_put_contents($tempFilePath, file_get_contents($fileUrl));

                    // Process the file
                    if (processFile($tempFilePath, $chatid, $message_id)) {
                        sendMessage($chatid, 'اطلاعات فایل ارسالی وارد دیتابیس شد', $message_id);
                        deleteSessionState($chatid); // Clear state after successful processing
                    } else {
                        $message  = 'Failed to process the file.';
                        sendMessage($chatid, $message , $message_id);
                    }

                    // Delete the temporary file
                    unlink($tempFilePath);
                }
            }
        } elseif (isset($update['message']['text'])) {
            // Handle message text
            $message = "Sorry, not a supported file or message. Please send the txt file format.";
            sendMessage($chatid, $message , $message_id);
        }
    }
}

}
