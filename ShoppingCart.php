<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "shopping_cart".
 *
 * @property integer $cart_id
 * @property double $amount
 * @property integer $product_id
 * @property integer $user_id
 * @property string $cartowner
 * @property string $created_at
 * @property string $session
 * @property string $website
 * @property double $qty
 */
class ShoppingCart extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'shopping_cart';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['amount', 'qty'], 'number'],
            [['product_id', 'created_at'], 'required'],
            [['product_id', 'user_id', 'cartowner'], 'integer'],
            [['created_at'], 'safe'],
            [['session'], 'string', 'max' => 32],
            [['website'], 'string', 'max' => 100],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'cart_id' => 'Cart ID',
            'amount' => 'Amount',
            'product_id' => 'Product ID',
            'user_id' => 'User ID',
            'cartowner' => 'Cartowner',
            'created_at' => 'Created At',
            'session' => 'Session',
            'website' => 'Website',
            'qty' => 'Qty',
        ];
    }
}
