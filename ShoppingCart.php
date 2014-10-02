<?php

namespace yz\shoppingcart;

use frontend\models\Product;
use yii\base\Component;
use yii\base\Event;
use Yii;
use \common\models\Cart;


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
    public function put($position, $quantity = 1)
    {
        if (isset($this->_positions[$position->getId()])) {
            $this->_positions[$position->getId()]->setQuantity(
                $this->_positions[$position->getId()]->getQuantity() + $quantity);
        } else {
            $position->setQuantity($quantity);
            $this->_positions[$position->getId()] = $position;
        }
        $this->trigger(self::EVENT_POSITION_PUT, new Event([
            'data' => $this->_positions[$position->getId()],
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'put', 'position' => $this->_positions[$position->getId()]],
        ]));
		$qty = $this->_positions[$position->getId()]->getQuantity();
		$this->saveToDb($position, $qty);
        $this->saveToSession();
    }

	public function saveToDb($position,$qty, $status=1){
		$erp= self::ID_ERP;
		$model = new Cart();
		if (!\Yii::$app->user->isGuest) {
			$id_user = \Yii::$app->user->getId();
			$model2 = $model->findOne(['id_erp'=>$position->$erp, 'id_user'=>$id_user]);
		}
		else {
			$id_user = null;
			$model2 = $model->findOne(['id_erp'=>$position->$erp, 'session'=>Yii::$app->session->id]);
		}
		if($model2) {
			$model2->qty= $qty;
            $model2->status = $status;
            if (!$model2->save()) {
                throw new \Exception('');
            }
		}
		else {
            $model->id_user = $id_user;
			$model->id_erp = $position->$erp;
			$model->qty = 1;
			$model->session = Yii::$app->session->id;
			$model->price = $position->price;
			if (!$model->save()) {
				throw new \Exception('');
			}
		}
	}
	/** save user to db on login for all lines where session = $session */
	public function saveUserToDB($session) { //problema : daca produsul se afla deja in cosul userului, trebuie facuta suma produselor.
		$model1 = new Cart();
		$model = $model1->findAll(['session'=>$session]);
		if(isset($model)) {
            //var_dump($model);die();
			foreach($model as $mods) {
                $model2 = Cart::findOne(['id_user'=>Yii::$app->user->getId(),'id_erp'=>$mods->id_erp]);
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
    public function update($position, $quantity)
    {
        if ($quantity <= 0) {
            $this->remove($position);
            $this->saveToDb($position,$quantity, $status = 0);
            return;
        }
        if (isset($this->_positions[$position->getId()])) {
            $this->_positions[$position->getId()]->setQuantity($quantity);

        } else {
            $position->setQuantity($quantity);
            $this->_positions[$position->getId()] = $position;
        }
        $this->trigger(self::EVENT_POSITION_UPDATE, new Event([
            'data' => 464,
        ]));
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'update', 'position' => $this->_positions[$position->getId()]],
        ]));
        $this->saveToDb($position,$quantity);
        $this->saveToSession();
    }

    /**
     * Removes position from the cart
     * @param CartPositionInterface $position
     */
    public function remove($position)
    {
        //var_dump($position);die();
        $erp= self::ID_ERP;
        //var_dump($this->_positions);die();
        if(array_key_exists($position->$erp, $this->_positions)) {
            $this->trigger(self::EVENT_BEFORE_POSITION_REMOVE, new Event([
                'data' => $this->_positions[$position->getId()],
            ]));
            $this->trigger(self::EVENT_CART_CHANGE, new Event([
                'data' => ['action' => 'remove', 'position' => $this->_positions[$position->getId()]],
            ]));
            unset($this->_positions[$position->getId()]);
            $this->saveToSession();
        }
        $this->saveToDb($position,0,0);
    }

    /**
     * Remove all positions
     */
    public function removeAll()
    {
        $erp= self::ID_ERP;
        //for session- >remove all from session
        $this->loadFromSession();
        $products = $this->_positions;
        foreach($products as $p) {
            $this->saveToDb($p, 0, 0);
        }
        $this->_positions = [];
        $this->trigger(self::EVENT_CART_CHANGE, new Event([
            'data' => ['action' => 'removeAll'],
        ]));
        $this->saveToSession();

        // remove all from cart for current user
        $models = Cart::findAll(['id_user'=>Yii::$app->user->getId(), 'status'=>1]);
        foreach ($models as $model) {
            $model2 = Product::findOne([$erp=>$model->id_erp]);
            if(!is_null($model2)) {
                $this->saveToDb($model2, 0, 0);
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
    public function getPositions()
    {
        $erp= self::ID_ERP;

        if (!\Yii::$app->user->isGuest) {
            $model= new Cart();
            $model2 = $model->findAll(['id_user'=>Yii::$app->user->getId(), 'status'=>1]);
            $prod= array();
            foreach($model2 as $model) {
                $obs = new Product();
                $obs = $obs->findOne([$erp=>$model->id_erp]);
                $prod[$model->id_erp] = $obs;
                $prod[$model->id_erp]->quantity = $model->qty;

            }
            return $prod;
        }
        else {
            return $this->_positions;
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
    public function getCost($withDiscount = false)
    {
        $cost = 0;
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
        Yii::$app->session[$this->cartId] = serialize($this->_positions);
        //var_dump(Yii::$app->session[$this->cartId]);
        //die();
    }

    protected function loadFromSession()
    {
        if (isset(Yii::$app->session[$this->cartId]))
            $this->_positions = unserialize(Yii::$app->session[$this->cartId]);
    }
}
