<?php

define('DEBUG', false);

define('SETTINGS_FILE', 'settings.ini');
define('SETTINGS_MAX_SEPARATOR_LENGTH', 25);

define('DEFAULT_SETTINGS', [
    'ORIGIN_URL'             => [ 'value' => 'https://tinder.com',  'type' => 'string' ],
    'BASE_URL'               => [ 'value' => 'api.gotinder.com',    'type' => 'string' ],
    'TINDER_VERSION'         => [ 'value' => '3.27.1',              'type' => 'string' ],

    'TOKEN_FILE'             => [ 'value' => 'token',               'type' => 'string' ],
    'FORBIDDEN_STRINGS_FILE' => [ 'value' => 'forbidden_strings',   'type' => 'string' ],
    'KNOWN_PROFILES_FILE'    => [ 'value' => 'known_profiles.json', 'type' => 'string' ],

    // Progress text max length
    'PROGRESS_MAX_LENGTH'    => [ 'value' => 80,                    'type' => 'int',    'comment' => 'characters' ],

    'MILES_IN_KM'            => [ 'value' => 1.60934,               'type' => 'float',  'comment' => 'kms to miles conversion ratio' ],

    // This is the user agent we're mocking.
    'USER_AGENT'             => [
        'value'     => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/101.0.4951.54 Safari/537.36',
        'type'      => 'string',
        'comment'   => 'User agent to use when making requests',
    ],

    // How much time to wait between requests if we're out of recommendations.
    'NO_RECOMMENDATIONS_RETRY_INTERVAL' => [ 'value' => 5, 'type' => 'int', 'comment' => 'minutes' ],

    /*
    * What's the maximum time to wait between requests once we've gone through all
    * the current set of recommendations.
    * 
    * This is a random number between 0 and this value.
    */
    'MAX_SLEEP_TIME'         => [ 'value' => 3,     'type' => 'int', 'comment' => 'seconds' ],

    // Minimum length of a bio to be considered relevant.
    'BIO_MIN_LENGTH'         => [ 'value' => 200,   'type' => 'int', 'comment' => 'characters' ],

    'AUTO_LIKE'                      => [ 'value' => true, 'type' => 'bool'   ],
    'ASK_BEFORE_LIKE'                => [ 'value' => true, 'type' => 'bool'   ],
    'ASK_BEFORE_LIKE_DEFAULT_CHOICE' => [ 'value' => 'n',  'type' => 'string', 'comment' => 'y/n' ],
    'AUTO_PASS'                      => [ 'value' => true, 'type' => 'bool'   ],

    'DISTANCE_TO_KM'        => [ 'value' => true,   'type' => 'bool' ]
]);

function loadSettings() {
    $settings = DEFAULT_SETTINGS;

    if (file_exists( SETTINGS_FILE )) {
        $iniSettings = file_get_contents( SETTINGS_FILE );

        foreach (explode(PHP_EOL, $iniSettings) as $line) {
            $line = trim($line);

            if (empty($line) || $line[0] == ';') { continue; }

            $parts = explode('=', $line, 2);

            if (count($parts) != 2) { continue; }

            $key    = trim($parts[0]);
            $value  = trim($parts[1]);

            $commentIndex = strpos($value, ';');
            if ($commentIndex !== false) {
                $value = trim( substr($value, 0, $commentIndex) );
            }

            if (isset($settings[$key])) {
                $wasValid = true;

                switch ($settings[$key]['type']) {
                    case 'int':
                        if (!is_numeric($value)) {
                            $wasValid = false;

                            break;
                        }

                        $value = (int) $value;

                        break;
                    case 'float':
                        if (!is_numeric($value)) {
                            $wasValid = false;

                            break;
                        }

                        $value = (float) $value;

                        if (!is_float($value)) {
                            $wasValid = false;

                            break;
                        }

                        break;
                    case 'bool':
                        if (empty($value) || $value == 'false') {
                            $value = false;
                        } else {
                            $value = true;
                        }

                        $value = (bool) $value;

                        break;
                    case 'string':
                        if ($value[0] == '"' && substr($value, -1) == '"') {
                            $value = substr($value, 1, -1);
                        }

                        $value = (string) $value;

                        break;
                }

                if (DEBUG) {
                    print ($wasValid ? '✓' : '✗') . ' ' . $key . PHP_EOL;
                }

                if (!$wasValid) {
                    trigger_error("Invalid value for setting \"$key\" (expected {$settings[$key]['type']}, got \"$value\"), using default value \"{$settings[$key]['value']}\"", E_USER_NOTICE);

                    continue;
                }
            } else {
                trigger_error("Unknown setting '$key'", E_USER_WARNING);
            }
        }
    } else {
        $maxSettingLength = 0;
        $maxValueLength   = 0;

        foreach ($settings as $key => $setting) {
            if (strlen($key) > $maxSettingLength) {
                $maxSettingLength = strlen($key);
            }

            if (strlen($setting['value']) > $maxValueLength) {
                $maxValueLength = strlen($setting['value']);
            }
        }

        if ($maxValueLength > SETTINGS_MAX_SEPARATOR_LENGTH) {
            $maxValueLength = SETTINGS_MAX_SEPARATOR_LENGTH;
        }

        file_put_contents(
            SETTINGS_FILE,
            '; This file was automatically generated by AutoMatch.'   . PHP_EOL .
            '; You can edit it to change the default settings.'       . PHP_EOL .
            '; Invalid settings will be ignored and cause a notice.'  . PHP_EOL .
            PHP_EOL
        );

        foreach ($settings as $key => $setting) {
            $setting['comment'] =
                isset($setting['comment'])
                    ? $setting['comment'] . ' (' . $setting['type'] . ')'
                    : $setting['type'];

            $line = $key;

            if (strlen($key) < $maxSettingLength) {
                $line .= str_repeat(' ', $maxSettingLength - strlen($key));
            }

            $line .= ' = ';

            $setting['value'] =
                $setting['type'] == 'bool'
                    ? ($setting['value'] ? 'true' : 'false')
                    : $setting['value'];

            $line .=
                strpos($setting['value'], ' ') !== false
                    ? '"' . $setting['value'] . '"'
                    : $setting['value'];

            if (strlen($setting['value']) < $maxValueLength) {
                $line .= str_repeat(' ', $maxValueLength - strlen($setting['value']));
            }

            $line .= ' ; ' . $setting['comment'] . PHP_EOL;

            file_put_contents( SETTINGS_FILE, $line, FILE_APPEND );
        }
    }

    foreach ($settings as $key => $setting) {
        define( $key, $setting['value'] );
    }
}

/**
 * request
 * 
 * Make a request to the Tinder API.
 *
 * @param  string       $url        URL to request
 * @param  string       $method     HTTP method to use
 * @param  array|null   $data       Data to send
 * 
 * @return array|null
 */
function request($url, $method = 'GET', $data = null) {
    $ch = curl_init();

    $url = 'https://' . BASE_URL . $url;

    if ($data && $method == 'GET') {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);

    curl_setopt($ch, CURLOPT_HTTPHEADER,     getHeaders());

    if ($data && $method != 'GET') {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $result = curl_exec($ch);

    if (curl_errno($ch) || curl_getinfo($ch, CURLINFO_HTTP_CODE) != 200) {
        throw new Exception('An unxpected error has occurred: ' . curl_error($ch) . ' - ' . $result);
    }

    curl_close($ch);

    $result = json_decode($result, true);

    if (
        $result
        &&
        isset($result['data']) && !empty($result['data'])
    ) {
        return $result['data'];
    }

    return null;
}

/**
 * requestAuthToken
 * 
 * Request an auth token for the Tinder API (must be provided by the user
 * from the browser).
 *
 * @param  bool $fromCrash  Whether this is being called from a crash
 * 
 * @return void
 */
function requestAuthToken($fromCrash = false) {
    global $authToken;

    print (
        $fromCrash
            ? 'Something went wrong.' . PHP_EOL .
              'Please try logging in again in a browser and typing a new token (press CTRL-C to abort): '
            : 'Please enter your Tinder auth token: '
    );

    $authToken = '';

    while (empty($authToken)) {
        $handle = fopen("php://stdin", "r");

        $authToken = trim(fgets($handle));

        if (!empty($authToken)) {
            file_put_contents(TOKEN_FILE, $authToken);
        }

        fclose($handle);

        if (empty($authToken)) {
            print 'You must supply a token, please try again: ';
        }
    }
}

/**
 * getSleepTime
 * 
 * Get the time to sleep between requests.
 * 
 * Returns a random number between 0 and MAX_SLEEP_TIME.
 *
 * @return int
 */
function getSleepTime() {
    return rand(0, MAX_SLEEP_TIME);
}

function removeTildes($string) {
    $string = str_replace(['á', 'à', 'ä', 'â', 'ã', 'ª'], 'a', $string);
    $string = str_replace(['Á', 'À', 'Â', 'Ä'], 'A', $string);
    $string = str_replace(['é', 'è', 'ë', 'ê'], 'e', $string);
    $string = str_replace(['É', 'È', 'Ê', 'Ë'], 'E', $string);
    $string = str_replace(['í', 'ì', 'ï', 'î'], 'i', $string);
    $string = str_replace(['Í', 'Ì', 'Ï', 'Î'], 'I', $string);
    $string = str_replace(['ó', 'ò', 'ö', 'ô', 'õ', 'º'], 'o', $string);
    $string = str_replace(['Ó', 'Ò', 'Ö', 'Ô', 'Õ'], 'O', $string);
    $string = str_replace(['ú', 'ù', 'ü', 'û'], 'u', $string);
    $string = str_replace(['Ú', 'Ù', 'Û', 'Ü'], 'U', $string);
    $string = str_replace(['ý', 'ÿ'], 'y', $string);
    $string = str_replace(['Ý'], 'Y', $string);

    return $string;
}

function removeEmojis($string) {
    $string = preg_replace('/[\x{1F300}-\x{1F64F}]/u', '', $string);
    $string = preg_replace('/[\x{1F680}-\x{1F6FF}]/u', '', $string);
    $string = preg_replace('/[\x{2600}-\x{26FF}]/u', '', $string);
    $string = preg_replace('/[\x{2700}-\x{27BF}]/u', '', $string);
    $string = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $string);

    return $string;
}

function removeLineBreaks($string) {
    return str_replace([ "\r", "\n" ], ' ', $string);    
}

/**
 * parseForbiddenStrings
 *
 * Parse the forbidden strings file and builds an array of forbidden strings
 * on top of the $forbiddenStrings global variable.
 *
 * Both the original strings and the version without tildes are added to the
 * array.
 *  
 * @return void
 */
function parseForbiddenStrings() {
    global $forbiddenStrings;

    if (!file_exists(FORBIDDEN_STRINGS_FILE)) {
        file_put_contents(FORBIDDEN_STRINGS_FILE, '');
    }

    $forbiddenStrings = [];

    foreach ( explode(PHP_EOL, file_get_contents(FORBIDDEN_STRINGS_FILE)) as $string ) {
        $string = trim($string);

        if (empty($string)) { continue; }

        if (!in_array($string, $forbiddenStrings)) {
            $forbiddenStrings[] = $string;
        }

        $withoutTildes = removeTildes($string);

        if (!in_array($withoutTildes, $forbiddenStrings)) {
            $forbiddenStrings[] = $withoutTildes;
        }
    }
}

/**
 * getHeaders
 * 
 * Get the headers of the API.
 * 
 * @param  bool $json  Whether to pass the required content-type header
 *
 * @return array
 */
function getHeaders($json = false) {
    global $authToken;

    $headers = [
        'Authority: ' . BASE_URL,
        'Accept: application/json',
        'Origin: '  . ORIGIN_URL,
        'Referer: ' . ORIGIN_URL . '/',
        'Tinder-Version: ' . TINDER_VERSION,
        'User-Agent: ' . USER_AGENT,
        'X-Auth-Token: ' . $authToken,
        'X-Supported-Image-Formats: webp,jpeg'
    ];

    if ($json) {
        $headers[] = 'Content-Type: application/json';
    }

    return $headers;
}

/**
 * getUser
 * 
 * Get the user data.
 *
 * @return array|null
 */
function getUser() {
    try {
        return request('/v2/profile', 'GET', [ 'include' => 'user' ]);
    } catch (Exception $e) {
        print 'Failed to fetch profile data: ' . $e->getMessage() . PHP_EOL;
    }

    return null;
}

/**
 * getRecs
 * 
 * Get the recommendations from Tinder's API.
 *
 * @return array
 */
function getRecs() {
    try {
        return request('/v2/recs/core', 'GET');
    } catch (Exception $e) {
        print 'Failed to fetch recommendations: ' . $e->getMessage() . PHP_EOL;

        exit(1);
    }
}

/**
 * storeKnownProfile
 *
 * Store a profile in the known profiles file.
 * 
 * @param  string $userId
 * 
 * @return void
 */
function storeKnownProfile($userId) {
    global $knownProfiles;

    if (!in_array($userId, $knownProfiles)) {
        $knownProfiles[] = $userId;
    }

    file_put_contents(KNOWN_PROFILES_FILE, json_encode($knownProfiles));
}

/**
 * readKnownProfiles
 * 
 * Read the known profiles from the file.
 *
 * @return bool True if the file was read, false otherwise.
 */
function readKnownProfiles() {
    global $knownProfiles;

    $knownProfiles = [];

    if (file_exists(KNOWN_PROFILES_FILE)) {
        try {
            $knownProfiles = json_decode(
                file_get_contents(KNOWN_PROFILES_FILE), // file
                true,                                   // assoc
                512,                                    // depth
                JSON_THROW_ON_ERROR                     // options
            );
        } catch (Exception $e) {
            print
                'Failed to decode known profiles: ' . $e->getMessage() . PHP_EOL .
                PHP_EOL .
                'The file will be deleted and a new one will be created.' . PHP_EOL;

            file_put_contents(KNOWN_PROFILES_FILE, json_encode($knownProfiles));

            return false;
        }
    } else {
        file_put_contents(KNOWN_PROFILES_FILE, json_encode($knownProfiles));
    }

    return true;
}

/**
 * pass
 *
 * @param  string $userId
 * 
 * @return void
 */
function pass($userId) {
    if (DEBUG) {
        print 'Passing ' . $userId . '...' . PHP_EOL;
    }

    storeKnownProfile($userId);

    try {
        request('/pass/' . $userId, 'GET');
    } catch (Exception $e) {
        print 'Failed to pass ' . $userId . ': ' . $e->getMessage() . PHP_EOL;

        return false;
    }

    return true;
}

/**
 * like
 *
 * @param  string $userId
 * 
 * @return void
 */
function like($userId) {
    if (DEBUG) {
        print 'Liking ' . $userId . '...' . PHP_EOL;
    }

    storeKnownProfile($userId);

    try {
        request('/like/' . $userId, 'POST');
    } catch (Exception $e) {
        print 'Failed to like ' . $userId . ': ' . $e->getMessage() . PHP_EOL;

        return false;
    }

    return true;
}

/**
 * bioHasRelevantText
 *
 * This method checks if the bio has relevant text. If the bio contains just an
 * Instagram username, it is not relevant.
 * 
 * If there's no bio, it is automatically irrelevant.
 * 
 * @param  mixed $bio
 * 
 * @return bool
 */
function bioHasRelevantText($bio) {
    global $irrelevantReason;

    $bio = strtolower($bio);

    // Try to remove the Instagram username
    $bio = preg_replace('/ig:.*[ ]/', '', strtolower($bio));
    $bio = preg_replace('/@.*[ ]/', '', $bio);

    $bio = trim($bio);

    if (empty($bio)) {
        $irrelevantReason = 'no text after removing Instagram username';

        return false;
    }

    if (strlen($bio) < BIO_MIN_LENGTH)  {
        $irrelevantReason = 'bio is too short';

        return false;
    }

    return true;
}

/**
 * bioHasForbiddenStrings
 *
 * This method checks if the bio has banned substrings.
 * 
 * @param  string $bio
 * 
 * @return bool
 */
function bioHasForbiddenStrings($bio) {
    global $forbiddenStrings;

    $bio = removeTildes(
        strtolower($bio)
    );

    foreach ($forbiddenStrings as $string) {
        if (strpos($bio, $string) !== false) { return true; }
    }
}

print 'Parsing settings... ' . (DEBUG ? PHP_EOL : '');
loadSettings();
print 'OK!' . PHP_EOL;

$irrelevantReason = null;

if (file_exists(TOKEN_FILE)) {
    $authToken = trim( file_get_contents(TOKEN_FILE) );

    if (empty($authToken)) { requestAuthToken(); }
} else {
    requestAuthToken();
}

print 'Parsing known profiles... ';
if (readKnownProfiles()) {
    print 'OK!' . PHP_EOL;
}

print 'Parsing forbidden strings... ';
parseForbiddenStrings();
print 'OK!' . PHP_EOL;

$user = null;

while (!$user) {
    print 'Getting user data... ';
    $user = getUser();

    if (!$user) {
        requestAuthToken(true);

        continue;
    }

    print 'OK!' . PHP_EOL;
}

print 'Getting recommendations... ';
$result = getRecs();
print 'OK!' . PHP_EOL;

print
    PHP_EOL .
    'Welcome ' . $user['user']['name'] . '!' . PHP_EOL .
    PHP_EOL;

$maxDistance = $user['user']['distance_filter'];

if (DISTANCE_TO_KM) {
    $maxDistance *= MILES_IN_KM;
}

print
    'Forbidden strings: ' . ($forbiddenStrings ? implode(', ', $forbiddenStrings) : '(none)') . PHP_EOL .
    'Age range: between ' . $user['user']['age_filter_min']  . ' and ' . $user['user']['age_filter_max'] . ' years.' . PHP_EOL .
    'Max distance: '      . $maxDistance . ' ' . (DISTANCE_TO_KM ? 'km' : 'mi') . '.' . PHP_EOL .
    PHP_EOL;

while (true) {
    while ($result && isset($result['results'])) {
        $recsCount = count($result['results']);

        foreach ($result['results'] as $index => $person) {
            $irrelevantReason = null;

            $photos = array_map(function ($photo) {
                return $photo['url'];
            }, $person['user']['photos']);

            if (DEBUG) {
                print
                    '==== SESSION START ===='              . PHP_EOL .
                    'Person: ' . $person['user']['name']   . PHP_EOL .
                    'Bio: '    . $person['user']['bio']    . PHP_EOL;
            }

            $bioHasRelevantText     = bioHasRelevantText($person['user']['bio']);
            $bioHasForbiddenStrings = bioHasForbiddenStrings($person['user']['bio']);

            if (DEBUG) {
                print '==== SESSION END ====' . PHP_EOL;
            }

            $age = null;

            if (isset($person['user']['birth_date'])) {
                $age = (new \DateTime())->diff(new \DateTime($person['user']['birth_date']))->y;
            }

            $distance = $person['distance_mi'];

            if (DISTANCE_TO_KM) {
                $distance *= MILES_IN_KM;
            }

            $distance = round($distance, 2);

            $message =
                (DEBUG ? '' : "\r") . '[ ' . ($index + 1) . '/' . $recsCount . ' ] ' . // [ #/# ]
                $person['user']['name'] . ' (' . ($age ? $age . 'y, ': '') . $distance . ' ' . (DISTANCE_TO_KM ? 'km' : 'mi') . '.): ';   // Name (age, distance)

            $message .= substr(
                removeLineBreaks( removeEmojis( $person['user']['bio'] ) ),  // input
                0,                                                           // start
                PROGRESS_MAX_LENGTH - strlen($message) - 1                   // length
            );
            
            print $message;

            if (!DEBUG) {
                // Clean the overriden line
                for ($i = 0; $i <= PROGRESS_MAX_LENGTH; $i++) {
                    print ' ';
                }
            }

            if (!$bioHasRelevantText || $bioHasForbiddenStrings) {
                if (!$bioHasRelevantText && DEBUG) {
                    print 'Bio has no relevant text (' . $irrelevantReason . '), skipping.' . PHP_EOL;
                }

                if ($bioHasForbiddenStrings && DEBUG) {
                    print 'Bio has forbidden strings, skipping.' . PHP_EOL;
                }

                if (AUTO_PASS) {
                    pass($person['user']['_id']);
                }

                continue;
            }

            if (!DEBUG) { print PHP_EOL; }

            print
                '===> ' . (AUTO_LIKE && !ASK_BEFORE_LIKE ? 'Liked' : 'Suggested') .
                ' person: ' . $person['user']['name'] . ' (' . $age . 'y, ' . $distance . ' ' . (DISTANCE_TO_KM ? 'km' : 'mi') . '.)' .
                ' with ID ' . $person['user']['_id'] . ',' .
                ' their bio says: ' . PHP_EOL . $person['user']['bio'] .
                PHP_EOL;

            if (AUTO_LIKE) {
                if (in_array($person['user']['_id'], $knownProfiles)) {
                    print 'API BUG: Already liked this person, forcing pass... ';

                    pass($person['user']['_id']);

                    print 'OK!' . PHP_EOL;

                    continue;
                }

                if (!ASK_BEFORE_LIKE) {
                    like($person['user']['_id']);
                } else {
                    while (
                        !isset($line)
                        ||
                        (
                            trim( strtolower($line) ) !== 'y'
                            &&
                            trim( strtolower($line) ) !== 'n'
                        )
                    ) {
                        print
                            'Do you like ' . $person['user']['name'] . '? (Yy|Nn|Pp = view photos) ['
                                . strtoupper(ASK_BEFORE_LIKE_DEFAULT_CHOICE) . strtolower(ASK_BEFORE_LIKE_DEFAULT_CHOICE) .
                            ']: ';

                        $handle = fopen('php://stdin', 'r');

                        $line = trim(
                            strtolower( fgets($handle) )
                        );

                        fclose($handle);

                        if (empty($line)) { $line = ASK_BEFORE_LIKE_DEFAULT_CHOICE; }

                        if ($line === 'y') {
                            like($person['user']['_id']);
                        } else if ($line === 'n') {
                            pass($person['user']['_id']);
                        } else if ($line === 'p') {
                            print 'Photos:' . PHP_EOL;

                            foreach ($photos as $photo) {
                                print ' - ' . $photo . PHP_EOL;
                            }
                        } else if (
                            $line !== 'y'
                            &&
                            $line !== 'n'
                            &&
                            $line !== 'p'
                        ) {
                            print 'Invalid choice, try again.' . PHP_EOL;
                        }
                    }

                    if (isset($line)) {
                        unset($line);
                    }
                }
            }
        }

        if (!DEBUG) { print PHP_EOL; }

        for ($i = getSleepTime( ); $i > 0; $i--) {
            print "\r" . 'Sleeping for ' . $i . ' second' . ($i == 1 ? '' : 's') . '... ';

            sleep(1);
        }

        print PHP_EOL;

        print 'Waiting for next batch... ';
        $result = getRecs();
        print 'OK!' . PHP_EOL;
    }

    print 'Ran out of recommendations, try increasing the distance filter.' . PHP_EOL;

    for ($remainingTime = (NO_RECOMMENDATIONS_RETRY_INTERVAL * 60); $remainingTime > 0; $remainingTime--) {
        print "\r" . 'Retrying in ' . $remainingTime . ' seconds... ';

        sleep(1);
    }

    print PHP_EOL;

    print 'Getting recommendations... ';
    $result = getRecs();
    print 'OK!' . PHP_EOL;
}

?>