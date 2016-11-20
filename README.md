# RedisFunc - A Functional Redis API that works on PRedis. 

RedisFunc is a functional-oriented PHP API, that lets programmers execute REDIS functions more quickly.
Functions work on any kind of data type, so you don't have to worry about different data types.
It is basically a set of global functions, so you can call them anywhere within your code, without having to initialize any objects.

It works on Predis!
So if you don't have Predis installed on your code, please follow this link (https://github.com/nrk/predis)
RedisFunc handles initializing Predis, so just install Predis with Composer and continue.

3 Things you have to do before using RedisFunc:
* Include RedisFunc.php
* Enter your database's connection parameters. (See CONNECTION section for how-to)
* Enter your database's structure and keys into the RedisFunc.php file. (See STRUCTURE section for how-to)


FUNCTIONS

The implemented functions are as  such:

* redisGet
* redisSet
* redisIncrement
* redisDelete
* redisKeyExists
* redisExistsOnSet
* redisUpdateOnSet
* redisDeleteFromSet
* redisGetKeys
* redisGetKeyCount
* redisLength
* redisGetKeyScore

The example codes below, run on a database with the following keys (see STRUCTURE section for how to create keys):
* REDIS_PRODUCTS: A sorted set where each key holds data of a product.
* REDIS_CART_PRODUCT_IDS: A set which stores the IDs of products inside a user's cart.
* REDIS_USERS: A hash that holds data of each user.
* REDIS_PRODUCT_VIEW_COUNT: String keys which store how many times a product has been viewed.
* REDIS_PURCHASE_PROCESSES : A list of the purchase IDs whose payments are queued to be processed.

redisGet($key, $args):

	STRING_DATA_TYPE keys:
	
	This line will bring the view count of product, whose ID is 5.
	$count = redisGet(REDIS_PRODUCT_VIEW_COUNT . ':5');

	HASH_DATA_TYPE keys:
	
	This will get the data of user whose ID is 10.
    $userData = redisGet(REDIS_USERS.":10");
	
	This will get only the phone number of the user.
    $phoneNumber = redisGet(REDIS_USERS.":10", ['field' => 'phone']);
	
	SET_DATA_TYPE keys:
	
	This line will get the IDs of the products on user's cart whose ID s 10
	$cartData = redisGet(REDIS_CART_PRODUCT_IDS.':10');
	
	SORTED_SET_DATA_TYPE keys:
	
	This line will bring the data of the products between 5th and 15th indexes in sorting manner (by scores).
	$data = redisGet(REDIS_PRODUCTS, [
        'start' => 5,
        'end' => 15,
        'sort' => 'desc') //possible values are desc or asc (descending or ascending). asc is default.
    ];
	
	This line will bring the data of all products.
	redisGet(REDIS_PRODUCTS);
	
	LIST_DATA_TYPE keys:
	
	This line will bring and delete the first item (left pop) from the process queue;
	$firstProcess = redisGet(REDIS_PURCHASE_PROCESSES);
	
	This line will bring and delete the last item (right pop) from the process queue;
	$firstProcess = redisGet(REDIS_PURCHASE_PROCESSES, ['right' => 'true']);

redisSet($key, $val, $args):

	STRING_DATA_TYPE keys:
    
	This line will set the view count of product whose ID is 5, to 40.
	redisSet($REDIS_PRODUCT_VIEW_COUNT . ':5', 40);
		
	HASH_DATA_TYPE keys:
	
	This will set the data of user whose ID is 10.
    redisSet(REDIS_USERS.":10",[
		'name' => 'Lionel',
		'lastName' => 'Messi',
		'phone' => '555-555-55-55',
		'age' => 28,
	]);
	
	This will set only the phone field of the user.
    $phoneNumber = redisGet(REDIS_USERS.":10", ['phone' => '555-555-55-55']);
		
	SET_DATA_TYPE keys:
	
	This line will add 7 (as productID) to user's cart whose ID s 10
	redisSet(REDIS_CART_PRODUCT_IDS.':10', 7);
		
	SORTED_SET_DATA_TYPE keys:
	
	This line will add apple, orange and banana to products. Their scores will be 1,4,7 respectively.
	redisSet(REDIS_PRODUCTS, [
			1 => 'apple',
			4 => 'orange',
			7 => 'banana'
		]);
		
	This line adds only pear, its score is 12.
	redisSet(REDIS_PRODUCTS, [
			12 => 'pear'
		]);

	LIST_DATA_TYPE keys:
	
	This line will add 7 (as processID) to left of the list (lpush)
	redisSet(REDIS_PURCHASE_PROCESSES, 7);
	
	This line will add 7 (as processID) to right of the list (rpush)
	redisSet(REDIS_PURCHASE_PROCESSES, 7, ['right' => 'true']);

IMMEDIATE CACHE:
** If you add an immediate = true field, on $args parameter of redisSet or redisGet functions, the values
will be saved on PHP's memory in runtime. This will cache them for access without calling redis. Use this
if you frequently need to access a key on your code.
Note that the values are not saved on a permanent space. The cache will be cleared after your PHP script is executed.

	This line will save the view count of product ID 5, on cache and return the value. If you get this key 
	later in the code, it will bring the data from the cache. Setting, updating and incrementing the value
	will also update the cache.
	$count = redisGet(REDIS_PRODUCT_VIEW_COUNT . ':5', ['immediate' => true]);

	
redisIncrement

	STRING_DATA_TYPE keys:
    
	This line will increment the view count of product whose ID is 5, by 1.
	redisIncrement($REDIS_PRODUCT_VIEW_COUNT . ':5', 1);
		
	HASH_DATA_TYPE keys:
	
	This will increment the age of user whose ID is 10, by 4. (you can use negative numbers as well)
    redisIncrement(REDIS_USERS.":10", 4, 'age');

redisDelete:

	*This function deletes a key, or keys matching a given regex, independent of their data types.

	This line will delete the data of the user whose ID is 7.
	redisDelete(REDIS_USERS.":7");
	
	This line will delete all users (the keys who start with the prefix REDIS_USERS)
	redisDelete(REDIS_USERS."*");
	
redisKeyExists:

	*This function returns a boolean denoting whether the given key exists on the database. It is 
	independent of the data type.
	
	This line will return whether there exists a user whose ID is 100.
	$exists = redisKeyExists(REDIS_USERS.":100");
	
redisExistsOnSet:
		
	*This function returns whether the given value exists on the set elements. It supports sets or 
	sorted sets, returns false on other data types.
	
	This line returns whether product ID 7 exists on the cart whose ID is 10.
	$exists = redisExistsOnSet(REDIS_CART_PRODUCT_IDS.':10', 7);
	
redisUpdateOnSet:

	*This function updates a value on a set. It supports sets or sorted sets.
	
	This line will update the age of the user to 22, whose ID is 8
	redisUpdateOnSet(REDIS_USERS.":8", 'age', 22);
	
redisDeleteFromSet:

	*This function deletes a value from a set. It supports sets or sorted sets.
	
	This line will delete  product ID 7 from the cart whose ID is 10.
	redisDeleteFromSet(REDIS_CART_PRODUCT_IDS.':10', 7);

redisGetKeys:

	*This function returns the keys beginning with the given key regex
	
	This line will return all keys that begin with REDIS_USERS key
	$keys = redisGetKeys(REDIS_USERS);
	
redisGetKeyCount:

        *This function returns only the count of the keys beginning with the given key regex
	
        This line will return how many keys exist begining with REDIS_USERS key
        $keys = redisGetKeyCount(REDIS_USERS);

redisLength:

	*This function returns
            *how many items exist on a set, if called on a set or sorted set.
            *how many keys exist that start with the given pattern
	
	This line will return how many items exist on the cart whose ID is 10.
	$count = redisLength(REDIS_CART_PRODUCT_IDS.':10');
	
redisGetKeyScore:

	*This function returns the score of an item on a SORTED_SET_DATA_TYPE. Returns false if called on 
	other data types.
	
	This line returns the score of orange on products sorted set. Returns -1 if not found. 
	$score = redisGetKeyScore(REDIS_PRODUCTS, 'orange');

	
CONNECTION
	
	Connecting to your Redis host is very easy on RedisFunc. Just go to line 10 on RedisFunc.php 
	and change the following global variables to your database's settings.
	
	//CONNECTION OPTIONS
    define('REDIS_CONN_SCHEME', 'tcp'); //put in your scheme here, it is usually tcp
    define('REDIS_CONN_HOST', '192.168.0.1'); //put in your database's ip number
    define('REDIS_CONN_PORT', 1234); //put in the database's connection port
	define('VENDOR_PATH', "vendor/autoload.php"); //path to the Composer installed components. 
	The folder should be named as "vendor"

	
STRUCTURE

	To define your database structure, you must do two things for each key you will have.
	* Define a global name and a character letter for each key. The character indicates how the keys 
	will be stored on the database in raw form. (more characters are allowed if you run out of characters)
	* Define the data type and expire options.
	
	Defining the name:
	
	Go to line 16 on RedisFunc.php. There are 5 defined keys here as example, you may delete them and 
	put in your own keys. The name (starting with REDIS_, altough you do not need to add this prefix), 
	is how you will be accessing the keys from your code, so you don't have to remember their actual 
	1-character values.
	The values are the keys that will be stored in the database. Just be careful not to enter overlapping keys.
	
	define('REDIS_PRODUCTS',         'A'); //The products are saved with keys like A:7, A:10. 
	//You can refer as REDIS_PRODUCTS.":7" in your code
	define('REDIS_CART_PRODUCT_IDS',   	'B');
	define('REDIS_USERS',             	'C');
	define('REDIS_PRODUCT_VIEW_COUNT', 	'4');
	define('REDIS_PURCHASE_PROCESSES', 	'xyz'); //Entering multiple letter values is not suggested. 
	//Do this only if you run out of characters, or if you want the raw data in database to be more human-readable
	
	Defining the data type and expire options:
	
	Go to line 23 on RedisFunc.php. $redisKeyConfig array stores the options for the keys. Enter the data 
	corresponding to your key as you can see on the PHP file.
	There are fields you have to enter for each key. If you don't enter them, they will have default values 
	which are defined below.
	
	*dataType: The data type of your key. Possible values are
		REDIS_SORTED_SET_DATA_TYPE
		REDIS_SET_DATA_TYPE
		REDIS_HASH_DATA_TYPE
		REDIS_STRING_DATA_TYPE
		REDIS_LIST_DATA_TYPE
	Default value is REDIS_STRING_DATA_TYPE.
	
	*expire: After how many seconds, the key shall expire. If you enter 0, the key will never expire unless 
	deleted manually by redisDelete function. There is no default value, you must enter this parameter.
	
	*extendExpire: The life time of the keys are automatically expired after every get operation. If you don't 
	want it to extend, enter ('extendExpire' => false) field. It is set to true by default.
		
    	$redisKeyConfig = [
        	REDIS_PRODUCTS => ['expire' => 28800, 'dataType' => REDIS_SORTED_SET_DATA_TYPE, 'extendExpire' => false],
        	REDIS_CART_PRODUCT_IDS => ['expire' => 1800, 'dataType' => REDIS_SET_DATA_TYPE, 'extendExpire' => false],
        	REDIS_USERS => ['expire' => 3600, 'dataType' => REDIS_HASH_DATA_TYPE],
        	REDIS_PRODUCT_VIEW_COUNT => ['expire' => 1800],
        	REDIS_PURCHASE_PROCESSES => ['expire' => 0, 'dataType' => REDIS_LIST_DATA_TYPE],
    	];
	
	
