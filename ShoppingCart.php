<?php

namespace yz\shoppingcart;

use common\components\Vat;
use frontend\models\Product;
use yii\base\Component;
use yii\base\Event;
use Yii;
use frontend\models\Cart;


/**
 * Class ShoppingCart
 * @property CartPositionInterface[] $positions
 * @property int $count Total count of positions in the cart
 * @property int $cost Total cost of positions in the cart
 * @property bool $isEmpty Returns true if cart is empty
 * @property string $hash Returns hash (md5) of the current cart, that is uniq to the current combination
 * of positions, quantities and costs
 * @package \yz\shoppingcart
 */
class ShoppingCart extends Component
{
    /** Triggered on position put */
    const EVENT_POSITION_PUT = 'putPosition';
    /** Triggered on position update */
    const EVENT_POSITION_UPDATE = 'updatePosition';
    /** Triggered on after position remove */
    const EVENT_BEFORE_POSITION_REMOVE = 'removePosition';
    /** Triggered on any cart change: add, update, delete position */
    const EVENT_CART_CHANGE = 'cartChange';
    /** Triggered on after cart cost calculation */
    const EVENT_COST_CALCULATION = 'costCalculation';

    /** To define de primary key from product model */
    const ID_ERP = 'remote_id_charisma';

    /**
     * Shopping cart ID to support multiple carts
     * @var string
     */
    public $cartId = __CLASS__;
    /**
     * @var array
     */
    public $discounts = [];
    /**
     * @var CartPositionInterface[]
     */
    protected $_positions = [];

    public function init()
    {
        $this->loadFromSession();
    }

    /**
     * @param CartPositionInterface $position
     * @param int $quantity
     */
    //todo scoatem wishlist_status????
    public function put($position, $quantity = 1, $wishlist, $wishlist_status=1) //scoatem wishlist_status????
    {
        if(is_null($wishlist)) {
            $id= "cart-".$position->getId();
        }
        else {
            $id= "wish-".$position->getId();
        }
        if (isset($this->_positions[$id])) {
            $this->_positions[$id]->
            setQuantity($this->_positions[$id]->getQuantity() + $quantity);
            $this->_positions[$id]->
            setWishlist($this->_positions[$id]->getWishlist());
        } else {
            $position->setQuantity($quantity);
            $this->_positions[$id] = $position;

            $position->setWishlist($wishlist);
            $this->_positions[$id] = $position;
        }


        $this->trigger(self::EVENT_POSITION_PUT, new Event([
            'data' => $this->_positions[$id],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'put', 'position' => $this->_positions[$id]],
        ]));
        $qty = $this->_positions[$id]->getQuantity();
        $this->saveToDb($position, $qty, 1 , $wishlist, $wishlist_status);
        $this->saveToSession();
    }

    public function saveToDb($position,$qty, $status=1, $wishlist=null, $wishlist_status){
        $website = Yii::$app->params['website'];
        $erp= self::ID_ERP;
        $model = new Cart();
        if (!\Yii::$app->user->isGuest) {
            $id_user = \Yii::$app->user->getId();
            $model2 = $model->findOne(['id_erp'=>$position->$erp, 'id_user'=>$id_user, 'wishlist'=>$wishlist, 'website'=>$website]);
        }
        else {
            $id_user = null;
            $model2 = $model->findOne(['id_erp'=>$position->$erp, 'session'=>Yii::$app->session->id,'wishlist'=>$wishlist, 'website'=>$website]);
        }
        if($model2) {
            $model2->qty = $qty;
            $model2->status = $status;
            $model2->discounted_price = $position->discountPrice;
            $model2->price=$position->price;
            $model2->wishlist = $wishlist;
            $model2->wishlist_status = $wishlist_status;
            if (!$model2->save()) {
                throw new \Exception('');
            }
        }
        else {
            $model->id_user = $id_user;
            $model->id_erp = $position->$erp;
            $model->qty = $qty;
            $model->session = Yii::$app->session->id;
            $model->discounted_price = $position->discountPrice;
            $model->price = $position->price;
            $model->wishlist = $wishlist;
            $model->wishlist_status = $wishlist_status;
            $model->website = $website;
            if (!$model->save()) {
                var_dump($model->getErrors());
                die();
            }
        }
    }
    /** save user to db on login for all lines where session = $session */
    /** @param int $session */
    public function saveUserToDB($session) { //problema : daca produsul se afla deja in cosul userului, trebuie facuta suma produselor.
        $model1 = new Cart();
        $model = $model1->findAll(['session'=>$session]);
        if(isset($model)) {
            foreach($model as $mods) {
                $model2 = Cart::findOne(['id_user'=>Yii::$app->user->getId(),'id_erp'=>$mods->id_erp, 'wishlist'=>$mods->wishlist]);
                if(!is_null($model2)) { //if product is already in user cart
                    $model2->qty = $mods->qty + $model2->qty;
                    $model2->status = 1;
                    $model2->save();
                    $mods->delete();
                }
                else {
                    $mods->id_user = Yii::$app->user->getId();
                    $mods->save();
                }
            }
        }
    }

    /**
     * @param CartPositionInterface $position
     * @param int $quantity
     */
    public function update($position, $quantity, $wishlist=null)
    {
        if ($quantity <= 0) {
            $this->remove($position, $wishlist);
            $this->saveToDb($position,$quantity, $status = 0, $wishlist, 1);
            return;
        }
        if(is_null($wishlist)) {
            $id= "cart-".$position->getId();
        }
        else {
            $id= "wish-".$position->getId();
        }
        //var_dump($this->_positions);die();
        if (isset($this->_positions[$id])) {
            $this->_positions[$id]->setQuantity($quantity);

        } else {
            $position->setQuantity($quantity);
            $this->_positions[$id] = $position;
        }
        $this->trigger(self::EVENT_POSITION_UPDATE, new Event([
            'data' => 464,
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'update', 'position' => $this->_positions[$id]],
        ]));
        $this->saveToDb($position,$quantity, 1, $wishlist, 1);
        $this->saveToSession();
    }

    /**
     * Removes position from the cart
     * @param CartPositionInterface $position
     */
    public function remove($position, $wishlist=null)
    {
        if(is_null($wishlist)) {
            $id= "cart-".$position->getId();
        }
        else {
            $id= "wish-".$position->getId();
        }
        //$erp= self::ID_ERP;
        if(array_key_exists($id, $this->_positions)) {
            $this->trigger(self::EVENT_BEFORE_POSITION_REMOVE, new Event([
                'data' => $this->_positions[$id],
            ]));
            $this->trigger(self::EVENT_CART_CHANGE, new Event([
                'data' => ['action' => 'remove', 'position' => $this->_positions[$id]],
            ]));
            unset($this->_positions[$id]);
            $this->saveToSession();
        }
        $this->saveToDb($position,0,0,$wishlist,1);
    }

    /**
     * Remove all positions
     */
    public function removeAll($wishlist=null)
    {
        $erp= self::ID_ERP;
        $this->loadFromSession();
        $products = $this->_positions;
        $wishlist_status= 1;
        foreach($products as $p) {
            $this->saveToDb($p, 0, 0,$wishlist, $wishlist_status);
        }
        $this->_positions = [];

        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'removeAll'],
        ]));
        $this->saveToSession();

        // remove all from cart for current user
        $models = Cart::findAll(['id_user'=>Yii::$app->user->getId(), 'wishlist'=>$wishlist]);
        foreach ($models as $model) {
            $model2 = Product::findOne([$erp=>$model->id_erp]);
            if(!is_null($model2)) {
                $this->saveToDb($model2, 0, 0, $wishlist, $wishlist_status);
            }
        }
    }

    public function deleteWishlist($wishlist='default') {
        $erp= self::ID_ERP;
        $this->loadFromSession();
        $products = $this->_positions;
        $wishlist_status= 0;
        foreach($products as $p) {
            $this->saveToDb($p, 0, 0,$wishlist, $wishlist_status);
        }
        $this->_positions = [];

        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'removeAll'],
        ]));
        $this->saveToSession();

        // remove all from cart for current user
        $models = Cart::findAll(['id_user'=>Yii::$app->user->getId(), 'wishlist'=>$wishlist]);
        foreach ($models as $model) {
            $model2 = Product::findOne([$erp=>$model->id_erp]);
            if(!is_null($model2)) {
                $this->saveToDb($model2, 0, 0, $wishlist, 0);
            }
        }
    }

    /**
     * Returns position by it's id. Null is returned if position was not found
     * @param string $id
     * @return CartPositionInterface|null
     */
    public function getPositionById($id)
    {
        if ($this->hasPosition($id))
            return $this->_positions[$id];
        else
            return null;
    }

    /**
     * Checks whether cart position exists or not
     * @param string $id
     * @return bool
     */
    public function hasPosition($id)
    {
        return isset($this->_positions[$id]);
    }

    /**
     * @return CartPositionInterface[]
     */
    public function getPositions($wishlist=null)
    {
        $erp= self::ID_ERP;
        if (!\Yii::$app->user->isGuest) {
            $model2 = (new Cart())->findAll(['id_user'=>Yii::$app->user->getId(), 'status'=>1,'wishlist'=>$wishlist]);
        }
        else {
            $model2 = (new Cart())->findAll(['id_user'=>null,'session'=>Yii::$app->session->getId(), 'status'=>1,'wishlist'=>$wishlist]);
        }
        $prod= array();
        foreach($model2 as $model) {
            $obs = (new Product())->findOne([$erp=>$model->id_erp]);
            $prod[$model->id_erp] = $obs;
            $prod[$model->id_erp]->quantity = $model->qty;
            $prod[$model->id_erp]->discountPrice = $model->discounted_price;
        }
        return $prod;
    }

    public function getWishlist($name= 'default', $status=1) {
        $erp= self::ID_ERP;
        $models = array();
        $prod= array();
        if (!\Yii::$app->user->isGuest) {
            $models = Cart::findAll(['id_user' => Yii::$app->user->getId(), 'wishlist' => $name, 'wishlist_status' => $status, 'status'=>1]);
            if(!is_null($models)) {
                foreach($models as $model) {
                    if (!is_null($model->id_erp)) {
                        $obs = new Product();
                        $obs = $obs->findOne([$erp => $model->id_erp]);
                        $prod[$model->id_erp] = $obs;
                        $prod[$model->id_erp]->quantity = $model->qty;
                        $prod[$model->id_erp]->discountPrice = $model->discounted_price;
                    }
                }
                return $prod;
            }
            else {
                return 'empty';
            }
        }
        else {
            $models = Cart::findAll(['id_user' =>null, 'wishlist' =>'default', 'session'=>Yii::$app->session->id,'wishlist_status' => $status, 'status'=>1]);
            if(!is_null($models)) {
                foreach($models as $model) {
                    $obs = new Product();
                    $obs = $obs->findOne([$erp=>$model->id_erp]);
                    $prod[$model->id_erp] = $obs;
                    $prod[$model->id_erp]->quantity = $model->qty;
                    $prod[$model->id_erp]->discountPrice = $model->discounted_price;
                }
                return $prod;
            }
            else {
                return 'empty';
            }
        }

    }
    /**
     * @param CartPositionInterface[] $positions
     */
    public function setPositions($positions)
    {
        $this->_positions = $positions;
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'positions'],
        ]));
        $this->saveToSession();
    }

    /**
     * Returns true if cart is empty
     * @return bool
     */
    public function getIsEmpty()
    {
        return count($this->_positions) == 0;
    }

    /**
     * @return int
     */
    public function getCount()
    {
        $count = 0;
        foreach ($this->_positions as $position)
            $count += $position->getQuantity();
        return $count;
    }

    /**
     * Return full cart cost as a sum of the individual positions costs
     * @param $withDiscount
     * @return int
     */
    //TODO ce facem in cazul in care produsul a fost adaugat in cos, si intre timp a fost modificat???
    public function getCost($wishlist=null,$withDiscount = false)
    {
        //$id_erp=self::ID_ERP;
        $cost = 0;
        $totalCost=0;
        $totalNet = 0;
        $totalDiscount=0;
        $totalCostNoDiscount = 0;
        if (\Yii::$app->user->isGuest) {
            foreach ($this->_positions as $position) {
                $cost += $position->getCost($withDiscount);
            }
            $costEvent = new CostCalculationEvent([
                'baseCost' => $cost,
            ]);
            $this->trigger(self::EVENT_COST_CALCULATION, $costEvent);
            if ($withDiscount)
                $cost -= $costEvent->discountValue;
            return $cost;
        }
        else {
            $models = Cart::findAll(['id_user'=>Yii::$app->user->getId(),'status'=>1,'wishlist'=>$wishlist]);
            foreach ($models as $model) {
                $price = $model->price;
                $vat = Product::findOne(['remote_id_charisma'=>$model->id_erp])->vat;
                if (!is_null($model->discounted_price)){
                    $price = $model->discounted_price;
                    $totalDiscount += ($model->price - $model->discounted_price)*$model->qty;
                }
                $totalCost += $price * $model->qty;
                $totalCostNoDiscount +=$model->price *$model->qty;
                $totalNet += Vat::removeVat($price, $vat);
            }
            return [
                'totalCost'=>$totalCost,
                'totalDiscount'=>$totalDiscount,
                'totalCostNoDiscount'=>$totalCostNoDiscount,
                'totalNet'=>$totalNet,
            ];
        }
    }

    /**
     * Returns hash (md5) of the current cart, that is unique to the current combination
     * of positions, quantities and costs. This helps us fast compare if two carts are the same, or not, also
     * we can detect if cart is changed (comparing hash to the one's saved somewhere)
     * @return string
     */
    public function getHash()
    {
        $data = [];
        foreach ($this->positions as $position) {
            $data[] = [$position->getId(), $position->getQuantity(), $position->getPrice()];
        }
        return md5(serialize($data));
    }

    protected function saveToSession()
    {
        /*
        echo "<pre>";
        var_dump($this->_positions);
        var_dump(Yii::$app->session[$this->cartId]);
        echo "</pre>";
        die();
        */
        Yii::$app->session[$this->cartId] = serialize($this->_positions);
    }

    protected function loadFromSession()
    {
        if (isset(Yii::$app->session[$this->cartId]))
            $this->_positions = unserialize(Yii::$app->session[$this->cartId]);
    }

    public function getAllWishlists($status=1){
        if (!\Yii::$app->user->isGuest) {
            $models = Cart::findAll(['id_user'=>Yii::$app->user->getId(),'wishlist_status'=>$status]);
            $wish= array();
            foreach($models as $model) {
                if (!is_null($model->wishlist)) {
                    $wish[] = $model->wishlist;
                }
            }
            $wishlists = array();
            foreach ($models as $mods) {
                if(in_array($mods->wishlist, $wish)) {
                    if ($mods->status == 1 && !is_null($mods->id_erp)) {
                        $wishlists[$mods->wishlist][] = $mods;
                    }
                }
            }
            foreach ($models as $empty) {
                if (!array_key_exists($empty->wishlist, $wishlists) && !is_null($empty->wishlist)) {
                    $wishlists[$empty->wishlist]=null;
                }
            }
            //die();
            return $wishlists;
        }
        else {
            return array();
        }
    }

    public static function createWishlist($name) {
        $exists = Cart::findAll(['wishlist'=>$name, 'wishlist_status'=>1, 'id_user'=>Yii::$app->user->getId()]);
        if(count($exists) == 0 ) {
            $cart = new Cart();
            $cart->session = Yii::$app->session->id;
            $cart->id_user = Yii::$app->user->getId();
            $cart->status = 0;
            $cart->wishlist = $name;
            $cart->wishlist_status = 1;
            $cart->save();
            return $cart;
        }
        else {
            return 'wishlist '.$name .' already exists';
        }
    }
    public static function renameWishlist($name, $newName) {
        if (!\Yii::$app->user->isGuest) {
            $exists = Cart::findAll(['wishlist' => $name, 'wishlist_status' => 1, 'id_user' => \Yii::$app->user->getId()]);
            if (count($exists) != 0) {
                foreach ($exists as $wish) {
                    $wish->wishlist = $newName;
                    $wish->save();
                }
                return true;
            } else {
                return false;
            }
        }
        else {
            return false;
        }
    }
    public function moveProdFromWishlist($product,$wishlist, $newWishlist) {
        $erp = self::ID_ERP;
        if (!\Yii::$app->user->isGuest) {
            $old= Cart::findAll(['wishlist'=>$wishlist, 'wishlist_status'=>1, 'id_user'=>\Yii::$app->user->getId()]);
            if (count($old) == 1) {
                $newWish = new Cart();
                $newWish->id_user = \Yii::$app->user->getId();
                $newWish->session = \Yii::$app->session->getId();
                $newWish->wishlist = $wishlist;
                $newWish->wishlist_status = 1;
                $newWish->save();
            }
            $wish = Cart::findOne(['id_erp'=>$product->$erp, 'wishlist'=>$wishlist, 'wishlist_status'=>1, 'id_user'=>\Yii::$app->user->getId()]);
            if(!is_null($wish)) {
                $this->saveToDb($product, $wish->qty, 1 , $newWishlist, 1);
                $wish->delete();
                return true;
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }
}
