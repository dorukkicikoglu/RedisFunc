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

if (isset($_POST['addProduct']))
    addProduct($_POST['productName']);
if (isset($_POST['deleteProduct']))
    deleteProduct($_POST['productName']);
if (isset($_POST['bringToTop']))
    bringProductUp(true, $_POST['productName']);
if (isset($_POST['bringToBottom']))
    bringProductUp(false, $_POST['productName']);
if (isset($_POST['deleteAllProducts']))
    deleteAllProducts();

if (isset($_POST['addUser']))
    addUser($_POST['userName'], $_POST['userAge'], $_POST['userGender']);
if (isset($_POST['deleteUser']))
    deleteUser ($_POST['userKey']);
if (isset($_POST['updateUser']))
    updateUser($_POST['userKey'], $_POST['userName'], $_POST['userAge'], $_POST['userGender']);
if (isset($_POST['deleteAllUsers']))
    deleteAllUsers();

/////////PRODUCTS FUNCTIONS/////////

function addProduct($productName){
    redisSet(REDIS_PRODUCTS, $productName);
}

function deleteProduct($productName){
    redisDeleteFromSet(REDIS_PRODUCTS, $productName);
}

function bringProductUp($top, $productName){
    redisDeleteFromSet(REDIS_PRODUCTS, $productName);
    if($top)
        $score = time() * -1;
    else $score = time(); 
    redisSet(REDIS_PRODUCTS, [$score => $productName]);  //minimum value needed to be at top. -1 * time(), current timestamp, will be the new score.
}

function deleteAllProducts(){
    redisDelete(REDIS_PRODUCTS.'*'); //deletes all keys starting with matching key
}

function getProductsCount() {
    return redisLength(REDIS_PRODUCTS);
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
                                <input type="hidden" value="' . $productName . '" name="productName" />
                                <input type="Submit" value="Delete" />
                            </form>
                        </div>	
                        &nbsp;&nbsp;	
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $productName . '" name="bringToBottom" />
                                <input type="hidden" value="' . $productName . '" name="productName" />
                                <input type="Submit" value="Bring To Bottom" />
                            </form>
                        </div>	
                        &nbsp;&nbsp;	
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $productName . '" name="bringToTop" />
                                <input type="hidden" value="' . $productName . '" name="productName" />
                                <input type="Submit" value="Bring To Top" />
                            </form>
                        </div>	
                    </div>
		';
    }
    return $html;
}

/////////USERS FUNCTIONS/////////

function addUser($name, $age, $gender){
    $cacheKey = REDIS_USERS.':'.time();

    redisSet($cacheKey, Array(
        'userName' => $name,
        'age' => $age,
        'gender' => $gender,
        'addDate' => date()
    ));
}

function updateUser($userKey, $name, $age, $gender){
    redisUpdateOnSet($userKey, 'userName', $name);
    redisUpdateOnSet($userKey, 'age', $age);
    redisUpdateOnSet($userKey, 'gender', $gender);
}

function deleteUser($userKey){
    redisDelete($userKey);
}

function deleteAllUsers(){
    redisDelete(REDIS_USERS.'*'); //deletes all keys starting with matching key
}

function getUsersCount() {
    return redisLength(REDIS_USERS);
}

function getUsersHTML() {
    $keys = redisGetKeys(REDIS_USERS);
    
    foreach ($keys as $userKey) {
        $userData = redisGet($userKey);
        $userName = $userData['userName'];
        $userAge = $userData['age'];
        $userGender = $userData['gender'];

        $html .= '
                    <div class="row">
                        <form method="post" style="float:left;">
                            <div class="cell"><input type="text" name="userName" value="' . $userName . '" /></div>	
                            <div class="cell"><input type="text" name="userAge" value="' . $userAge . '" /></div>	
                            <div class="cell"><input type="text" name="userGender" value="' . $userGender . '" /></div>		
                            <input type="hidden" name="updateUser" />
                            <input type="hidden" name="userKey" value="'.$userKey.'" />
                            <input type="Submit" value="Save" />
                        </form>
                        <div class="rowButton">
                            <form method="post">
                                <input type="hidden" value="' . $userKey . '" name="deleteUser" />
                                <input type="hidden" value="' . $userKey . '" name="userKey" />
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
            
            <div style="margin-top: 9px;width: 608px;padding-bottom: 2px;">
                There are <?php echo getUsersCount(); ?> users!
                <form method="post" style="float: right;">
                    <input type="Submit" name="deleteAllUsers" value="Delete All Users!" />
                </form>
            </div>

            <div class="rowsList usersList" style="width:610px;"/>  
                <?php echo getUsersHTML(); ?>
            </div>
        </div>

</body>
</html>