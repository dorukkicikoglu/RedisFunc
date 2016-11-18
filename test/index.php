<?php

/*
    This Demo file tests the following functions
    - redisSet (on sorted_set, hash)
    - redisUpdateOnSet (on hash)
    - redisDelete (on sorted_set, hash)
    - redisDeleteFromSet (on sorted_set, hash)
    - redisLength (on sorted_set, set)
    - redisGetKeys (on all data types)
*/

include_once "RedisFunc.php";

/////////PRODUCTS/////////

if (isset($_POST['productName'])) {
    redisSet(REDIS_PRODUCTS, $_POST['productName']);
}
if (isset($_POST['deleteProduct'])) {
    redisDeleteFromSet(REDIS_PRODUCTS, $_POST['deleteProduct']);
}
if (isset($_POST['bringToTop'])) {
    redisDeleteFromSet(REDIS_PRODUCTS, $_POST['bringToTop']);
    redisSet(REDIS_PRODUCTS, [time() * -1 => $_POST['bringToTop']]);  //minimum value needed to be at top. -1 * time(), current timestamp, will be the new score.
}
if (isset($_POST['bringToBottom'])) {
    redisDeleteFromSet(REDIS_PRODUCTS, $_POST['bringToBottom']);
    redisSet(REDIS_PRODUCTS, [time() => $_POST['bringToBottom']]);  //maximum value needed to be at bottom. time(), current timestamp, will be the new score.
}
if (isset($_POST['deleteAllProducts'])) {
    redisDelete(REDIS_PRODUCTS); //deletes all keys starting with matching key
}

function getProductsHTML() {
    $data = redisGet(REDIS_PRODUCTS);
    $html = '';
    foreach ($data as $productName) {
        $html .= '
                    <div class="row">
                        <div class="text">' . $productName . '</div>	
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $productName . '" name="deleteProduct" />
                                <input type="Submit" value="Delete" />
                            </form>
                        </div>	
                        &nbsp;&nbsp;	
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $productName . '" name="bringToTop" />
                                <input type="Submit" value="Bring To Top" />
                            </form>
                        </div>	
                        &nbsp;&nbsp;	
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $productName . '" name="bringToBottom" />
                                <input type="Submit" value="Bring To Bottom" />
                            </form>
                        </div>	
                    </div>
		';
    }
    return $html;
}

function getProductsCount() {
    return redisLength(REDIS_PRODUCTS);
}

/////////USERS/////////

if (isset($_POST['userName'])) {
    addUser();
}
if (isset($_POST['deleteUser'])) {
    redisDelete($_POST['deleteUser']);
}
if (isset($_POST['updateUser'])) {
    updateUser($_POST['updateUser']);
}
if (isset($_POST['deleteAllUsers'])) {
    redisDelete(REDIS_USERS.'*'); //deletes all keys starting with matching key
}

function addUser(){
    $cacheKey = REDIS_USERS.':'.time();

    redisSet($cacheKey, Array(
        'userName' => $_POST['userName'],
        'age' => $_POST['userAge'],
        'gender' => $_POST['userGender'],
        'addDate' => date()
    ));
}

function updateUser($userKey){
    redisUpdateOnSet($userKey, 'userName', $_POST['editUserName']);
    redisUpdateOnSet($userKey, 'age', $_POST['editUserAge']);
    redisUpdateOnSet($userKey, 'gender', $_POST['editUserGender']);
}

$usersCount;
function getUsersHTML() {
    global $usersCount;
    $keys = redisGetKeys(REDIS_USERS);
    $usersCount = count($keys);
    
    foreach ($keys as $userKey) {
        $userData = redisGet($userKey);
        $userName = $userData['userName'];
        $userAge = $userData['age'];
        $userGender = $userData['gender'];

        $html .= '
                    <div class="row">
                        <form method="post" style="float:left;">
                            <div class="cell"><input type="text" name="editUserName" value="' . $userName . '" /></div>	
                            <div class="cell"><input type="text" name="editUserAge" value="' . $userAge . '" /></div>	
                            <div class="cell"><input type="text" name="editUserGender" value="' . $userGender . '" /></div>		
                            <input type="hidden" name="updateUser" value="'.$userKey.'" />
                            <input type="Submit" value="Save" />
                        </form>
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $userKey . '" name="deleteUser" />
                                <input type="Submit" value="Delete" />
                            </form>
                        </div>	
                    </div>
		';
    }
    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>

        <meta name="description" content="Redis Func Test">
        <title>Redis Func Test</title>

        <style>
            .menuGroup{float:left;margin:5px;}
            .rowsList{width: 410px;}
            .rowsList .row{height: 20px;border-top: 1px solid #000;padding: 2px;padding-left: 0px;}
            .rowsList .row .text{float:left;}
            .rowsList .row .rowButton{float:right;}
            .rowsList .row .cell{float:left;border-left: 1px solid #000;width:140px;overflow:hidden; padding-left: 2px;}
            
        </style>

    </head>

    <body>

        <div class="productsMenu menuGroup">
            <form method="post">
                <label for="productName">Add Product: &nbsp;&nbsp;</label>
                <input type="text" id="productName" name="productName" />
                <input type="Submit" name="addProduct" value="Add!" />
            </form>

            <div style="margin-top: 9px;width: 407px;padding-bottom: 2px;">
                You have <?php echo getProductsCount(); ?> products!
                <form method="post" style="float: right;">
                    <input type="Submit" name="deleteAllProducts" value="Delete All Products!" />
                </form>
            </div>

            <div class="rowsList productsList"/>  
                <?php echo getProductsHTML(); ?>
            </div>
        </div>

        <div class="usersMenu menuGroup">
            <form method="post">
                <label for="userName">Full Name: &nbsp;&nbsp;</label>
                <input type="text" id="userName" name="userName" />
                <label for="userName">Age: &nbsp;&nbsp;</label>
                <input type="text" id="userAge" name="userAge" />
                <label for="userName">Gender: &nbsp;&nbsp;</label>
                <select id="userGender" name="userGender">
                    <option value="female">Female</option>
                    <option value="male">Male</option>
                </select>
                <input type="Submit" name="addUser" value="Add User!" />
            </form>
            
            <?php $usersHTML = getUsersHTML(); ?>
            <div style="margin-top: 9px;width: 608px;padding-bottom: 2px;">
                There are <?php echo $usersCount; ?> users!
                <form method="post" style="float: right;">
                    <input type="Submit" name="deleteAllUsers" value="Delete All Users!" />
                </form>
            </div>

            <div class="rowsList usersList" style="width:610px;"/>  
                <?php echo $usersHTML; ?>
            </div>
        </div>

</body>
</html>