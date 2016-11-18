<?php
    $Redis = false; //this is the global predis object 
	
    define('REDIS_STRING_DATA_TYPE', 1);
    define('REDIS_HASH_DATA_TYPE', 2);
    define('REDIS_SET_DATA_TYPE', 3);
    define('REDIS_SORTED_SET_DATA_TYPE', 4);
    define('REDIS_LIST_DATA_TYPE', 5);
   
	//CONNECTION OPTIONS
    define('REDIS_CONN_SCHEME', 'tcp');
    define('REDIS_CONN_HOST', '127.0.0.1');
    define('REDIS_CONN_PORT', 6379);
    define('VENDOR_PATH', "vendor/");
	
	//For every redis key you will create, you must first define a string to them. Suggested is naming it with a single character for minimal memory use.
    define('REDIS_PRODUCTS',         	'A');
    define('REDIS_CART_PRODUCT_IDS',   	'B');
    define('REDIS_USERS',             	'C');
    define('REDIS_PRODUCT_VIEW_COUNT', 	'4');
    define('REDIS_PURCHASE_PROCESSES', 	'xyz');

	//After defining, define their data types and expire options on $redisKeyConfig array.
    $redisKeyConfig = [
        REDIS_PRODUCTS => ['expire' => 28800, 'dataType' => REDIS_SORTED_SET_DATA_TYPE, 'extendExpire' => false],
        REDIS_CART_PRODUCT_IDS => ['expire' => 1800, 'dataType' => REDIS_SET_DATA_TYPE, 'extendExpire' => false],
        REDIS_USERS => ['expire' => 3600, 'dataType' => REDIS_HASH_DATA_TYPE],
        REDIS_PRODUCT_VIEW_COUNT => ['expire' => 1800],
        REDIS_PURCHASE_PROCESSES => ['expire' => 0, 'dataType' => REDIS_LIST_DATA_TYPE],
    ];
    
	define('REDIS_EMPTY_SET_MARKER', 'xy23AB1'); //added on empty sets to mark them as empty and prevent from being deleted
    $immediateCache = Array();

    function initRedis() {
        global $Redis;

        require_once(VENDOR_PATH . "autoload.php");

        if (!$Redis) {
            Predis\Autoloader::register();

            $Redis = new Predis\Client([
                'scheme' => REDIS_CONN_SCHEME,
                'host' => REDIS_CONN_HOST,
                'port' => REDIS_CONN_PORT
            ]);
        }
        return $Redis;
    }

    //gets the data at a key regardless of data type
    //args can have following fields: immediate, start, end, sort, field (hash only)
    function redisGet($key, $args = Array()) {
        global $immediateCache, $redisKeyConfig;
        if (isset($immediateCache[$key]))
            return $immediateCache[$key];

        $Redis = initRedis();
        $keyIndex = redisGetKeyIndex($key);
        $noSaveImmediate = false;

        $keyConfig = redisGetKeyCongif($key);
        $dataType = redisGetKeyDataType($key);

        if ($dataType == REDIS_HASH_DATA_TYPE) {
            if(isset($args['field']))
                $data = $Redis->hget($key, $args['field']);
            else if(isset($args['fields'])) {
                $rawData = $Redis->hmget($key, $args['fields']);
                
                if(is_array($rawData)){
                    $data = Array();
                    for($i = 0; $i < count($args['fields']); $i++){
                        $thisField = $args['fields'][$i];
                        $data[$thisField] = $rawData[$i];
                    }
                } else $data = $rawData;
                
            } else $data = $Redis->hgetall($key);
        } else if ($dataType == REDIS_SET_DATA_TYPE) {
            $data = $Redis->smembers($key);
            $data = redisRemoveEmptyMarker($data);
            
            if(!$data)
                return Array();
        } else if ($dataType == REDIS_SORTED_SET_DATA_TYPE) {
            if (isset($args['start']) && isset($args['end'])) {
                $start = $args['start'];
                $end = $args['end'];
                $noSaveImmediate = true;
            } else {
                $start = 0;
                $end = -1;
            }

            if (isset($args['sort']) && strtolower($args['sort']) == 'desc')
                $data = $Redis->zrevrange($key, $start, $end);
            else
                $data = $Redis->zrange($key, $start, $end);

            $data = redisRemoveEmptyMarker($data);
            
            if(!$data)
                return Array();
        } else if ($dataType == REDIS_LIST_DATA_TYPE) {
            if(isset($args['right']))
                $data = $Redis->rpop($key);
            else $data = $Redis->lpop($key);
        } else { //string data type
            $data = $Redis->get($key);
        }

        $extendExpire = !(isset($keyConfig['extendExpire']) && $keyConfig['extendExpire'] == false);
        if($extendExpire && isset($keyConfig['expire'])){
            $timeout = $keyConfig['expire'];
            if($timeout > 0)
                $Redis->expire($key, $timeout);
        }
        
        if (!$data)
            return false;

        if (!$noSaveImmediate && (isset($args['immediate']) && $args['immediate'] == true))
            $immediateCache[$key] = $data;

        return $data;
    }

    //inserts data (or array of data) at given key
    function redisSet($key, $val, $args = Array()) {
        $Redis = initRedis();

        $keyIndex = redisGetKeyIndex($key);
        $keyConfig = redisGetKeyCongif($key);
        $ttl = $Redis->ttl($key);

        $keyExisted = redisKeyExists($key);

        $dataType = redisGetKeyDataType($key);
        
        if ($dataType == REDIS_HASH_DATA_TYPE) {
            $Redis->hmset($key, $val);
        } else if ($dataType == REDIS_SET_DATA_TYPE) {
            if (!is_array($val))
                $val = Array($val);

            if (empty($val) && redisLength($key) == 0) //special item for empty array, so redis will not erase the key
                $val[] = REDIS_EMPTY_SET_MARKER;
            
            for ($i = 0; $i < count($val); $i++)
                $Redis->sadd($key, $val[$i]);
            
            redisRemoveEmptyMarker($key);
        } else if ($dataType == REDIS_SORTED_SET_DATA_TYPE) {
            /*
              expected array -> each key is the score, each field is an array of items to be added with the score
             */

            if (empty($val) && redisLength($key) == 0) //special item for empty array, so redis will not erase the key
                $val = Array(REDIS_EMPTY_SET_MARKER);
            
            if (!is_array($val))
                $val = Array($val);
            
            foreach ($val as $score => $values) {
                if (!is_array($values))
                    $values = Array($values);

                for ($i = 0; $i < count($values); $i++) {
                    $Redis->zadd($key, $score, $values[$i]);
                }
            }
            redisRemoveEmptyMarker($key);
        } else if ($dataType == REDIS_LIST_DATA_TYPE) {
            if(isset($args['right']))
                $Redis->rpush($key, $val);
            else $Redis->lpush($key, $val);
        } else {
            $Redis->set($key, $val);
        }

        if($ttl <= 0){
            $timeout = $keyConfig['expire'];
        } else {
            $extendExpire = !(isset($keyConfig['extendExpire']) && $keyConfig['extendExpire'] == false);
            if($extendExpire)
                $timeout = $keyConfig['expire'];
            else $timeout = $ttl;
        }
        if($timeout > 0)
            $Redis->expire($key, $timeout);
       
        global $immediateCache;
        if ($dataType != REDIS_SORTED_SET_DATA_TYPE && $dataType != REDIS_SET_DATA_TYPE){ //we can not save sorted hashes on set
            //set immediate cache 
            if ((isset($args['immediate']) && $args['immediate'] == true) || isset($immediateCache[$key]))
                $immediateCache[$key] = $val;
        } else {
            unset($immediateCache[$key]);
        }

        return $val;
    }

    //increments the value of the key, or the field on the key if it is a set or sorted set
    function redisIncrement($key, $offset, $field = false) {
        $dataType = redisGetKeyDataType($key);
        $Redis = initRedis();

        if ($dataType == REDIS_STRING_DATA_TYPE){
            $newVal = $Redis->incrby($key, $offset);
            if (isset($immediateCache[$key]))
                $immediateCache[$key] = $newVal;

            return $newVal;
        } else if($dataType == REDIS_HASH_DATA_TYPE){
             $newVal = $Redis->hincrby($key, $field, $offset);
             if (isset($immediateCache[$key][$field]))
                $immediateCache[$key][$field] = $newVal;

            return $newVal;
        }
    }

    //deletes the redis key or keys with *regex*
    function redisDelete($key) {
        $Redis = initRedis();

        //if this is not a regex
        if(strpos($key, '*') === false) {
            $Redis->expire($key, 0);
            return;
        }

        $keys = $Redis->keys($key);
        foreach ($keys as $keyArr => $val)
            $Redis->del($val);
    }

    //returns what ($redisKeyConfig) index the key belongs to
    function redisGetKeyIndex($key){
        $pos = strpos($key, ':');
        if($pos === false)
            return $key;
        return substr($key, 0, $pos);
    }

    //returns whether the given key exists on redis
    function redisKeyExists($key) {
        $Redis = initRedis();
        return $Redis->exists($key);
    }

    //returns whether the given value exists on the key, used in sets or sorted sets
    function redisExistsOnSet($key, $val){
        $dataType = redisGetKeyDataType($key);

        if ($dataType != REDIS_SORTED_SET_DATA_TYPE && $dataType != REDIS_SET_DATA_TYPE)
            return false;

        $Redis = initRedis();
        if($dataType == REDIS_SORTED_SET_DATA_TYPE){
            $address = $Redis->zrank($key, $val);
            return is_numeric($address);
        } else {
            return $Redis->sismember($key, $val);
        }
    }

    //updates the field on the given key (Sets or sorted sets)
    function redisUpdateOnSet($key, $field, $val){
        $dataType = redisGetKeyDataType($key);

        if ($dataType != REDIS_HASH_DATA_TYPE)
            return false;

        $Redis = initRedis();
        if ($dataType == REDIS_HASH_DATA_TYPE){
            return $Redis->hset($key, $field, $val);
        }
    }

    //deletes the field on the given key (Sets or sorted sets)
    function redisDeleteFromSet($key, $val){
        $dataType = redisGetKeyDataType($key);

        if ($dataType != REDIS_SORTED_SET_DATA_TYPE && $dataType != REDIS_SET_DATA_TYPE)
            return false;

        $Redis = initRedis();
        if($dataType == REDIS_SORTED_SET_DATA_TYPE)
            $Redis->zrem($key, $val);
        else $Redis->srem($key, $val);
        
        if(redisLength($key) == 0)
            redisSet ($key, REDIS_EMPTY_SET_MARKER); //to put empty marker in case the cache is now empty
    }

    //returns the keys beginning with the given key regex
    function redisGetKeys($key){
        $Redis = initRedis();
        if(strpos($key, '*') === false)
            $key .= '*';
        return $Redis->keys($key);
    }

    //returns how many items exist on the key
    function redisLength($key){
        $dataType = redisGetKeyDataType($key);

        if ($dataType != REDIS_SORTED_SET_DATA_TYPE && $dataType != REDIS_SET_DATA_TYPE)
            return false;

        $Redis = initRedis();
        if($dataType == REDIS_SORTED_SET_DATA_TYPE){
            $count = $Redis->zcount($key, '-inf', '+inf');
        } else {
            $count = $Redis->scard($key);
        }
        if(!$count)
            return 0;
        
        if($count == 1 && redisExistsOnSet($key, REDIS_EMPTY_SET_MARKER))
            return 0;
        
        return $count;
    }

    //returns the data type of a certain key
    function redisGetKeyDataType($key){
        $config = redisGetKeyCongif($key);
        if(isset($config['dataType']))
            return $config['dataType'];
        return REDIS_STRING_DATA_TYPE;
    }

    //return entire config array for the cache
    function redisGetKeyCongif($key){
        global $redisKeyConfig;

        $keyIndex = redisGetKeyIndex($key);
        if (isset($redisKeyConfig[$keyIndex]))
            return $redisKeyConfig[$keyIndex];
        return Array();
    }
    
    //get the score of first instance in key
    //returns false if not found
    function redisGetKeyScore($key, $val){
        $dataType = redisGetKeyDataType($key);

        if ($dataType != REDIS_SORTED_SET_DATA_TYPE)
            return false;

        $Redis = initRedis();
        $score = $Redis->zscore($key, $val);
        return $score;
    }

    //if there is now no need for an empty marker, remove it
    //if $arg is a string, it removes the marker from the redis cache
    //if $arg is an array, it removes the marker from the array directly - used for clearing redisGet responses
    function redisRemoveEmptyMarker($arg){
        if(is_string($arg)){
            if(redisLength($arg) == 2)
                redisDeleteFromSet($arg, REDIS_EMPTY_SET_MARKER);
        } else if(is_array($arg)){
            if(isset($arg[0]) && $arg[0] == REDIS_EMPTY_SET_MARKER)
                $arg = Array();
        }
        return $arg;
    }
    
?>