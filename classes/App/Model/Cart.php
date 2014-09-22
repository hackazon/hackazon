<?php

namespace App\Model;

/**
 * Class Cart
 * @package App\Model
 * @property Product $products Products residing in cart.
 */
class Cart extends \PHPixie\ORM\Model {

    public $table = 'tbl_cart';
    public $id_field = 'id';
    private $_cart;
    const STEP_OVERVIEW  = 1;
    const STEP_SHIPPING  = 2;
    const STEP_BILLING   = 3;
    const STEP_CONFIRM   = 4;
    const STEP_ORDER     = 5;

    protected $has_one=array(

        //Set the name of the relation, this defines the
        //name of the property that you can access this relation with
        'shippingAddress' => array(
            'model' => 'CustomerAddress',
            'key' => 'id',
            'foreignKey' => 'shipping_address_id'
        )
    );

    protected $has_many = array(
        'products' => array(
            'model' => 'Product',
            'through' => 'tbl_cart_items',
            'foreign_key' => 'product_id',
            'key' => 'cart_id'
        )
    );

    /**
     * get current cart
     * @return Cart
     */
    public function getCart(){
        if (empty($this->_cart)) {
            $this->setCart();
        }
        return $this->_cart;
    }

    private function setCart()
    {
        if (!session_id())
            session_start();
        $uid = session_id();
        $cart = $this->getCartByUID($uid);
        if (!$cart) {
            $cart = $this->createNewCart($uid);
        }
        $this->_cart = $cart;
    }

    /**
     * Create new cart by session_id
     * @param string $uid session_id
     * @return Cart
     */
    private function createNewCart($uid)
    {
        $this->pixie->db->query('insert')->table('tbl_cart')
            ->data(array('uid' => $uid, 'created_at' => date('Y-m-d H:i:s')))
            ->execute();
        $lastId = $this->pixie->db->insert_id();
        return $this->pixie->orm->get('Cart')->where('id',$lastId)->find();
    }

    /**
     * @param int $uid
     * @return Cart|bool
     */
    private function getCartByUID($uid)
    {
        $cart = $this->pixie->orm->get('Cart')->where('uid',$uid)->find();
        return $cart->loaded() ? $cart : false;
    }

    /**
     * @param int $addressId
     * @param string $type (shipping|billing)
     */
    public function updateAddress($addressId, $type)
    {
        $cart = $this->getCart();
        $cart->{$type . '_address_id'} = $addressId;
        $cart->save();
        $step = $type == 'shipping' ? self::STEP_BILLING : self::STEP_CONFIRM;
        $this->updateLastStep($step);
    }

    /**
     * update last confirmed step
     * @param int $step
     */
    public function updateLastStep($step)
    {
        $cart = $this->getCart();
        if ($step > $cart->last_step) {
            $cart->last_step = $step;
            $cart->save();
        }
    }

    /**
     * return label by last step
     * @return string
     */
    public function getStepLabel()
    {
        $cart = $this->getCart();
        $step = $cart->last_step;
        switch ($step) {
            case self::STEP_OVERVIEW :
                return 'overview';
            case self::STEP_SHIPPING :
                return 'shipping';
            case self::STEP_BILLING :
                return 'billing';
            case self::STEP_CONFIRM :
                return 'confirmation';
            case self::STEP_ORDER :
                return 'order';
            default :
                return 'overview';
        }
    }

    /**
     * @return array|CustomerAddress
     */
    public function getShippingAddress()
    {
        $row = $this->pixie->orm->get('CustomerAddress')->where('and', array('id', '=', $this->shipping_address_id), array('customer_id', '=', $this->pixie->auth->user()->id))->find();
        if ($row->loaded()) {
            return $row;
        }
        return array();
    }

    /**
     * @return array|CustomerAddress
     */
    public function getBillingAddress()
    {
        $row = $this->pixie->orm->get('CustomerAddress')->where('and', array('id', '=', $this->billing_address_id), array('customer_id', '=', $this->pixie->auth->user()->id))->find();
        if ($row->loaded()) {
            return $row;
        }
        return array();
    }

    public function getCartItemsModel()
    {
        return $this->pixie->orm->get('CartItems');
    }

    /**
     * set customer_id and email to cart
     */
    public function setCustomer()
    {
        $this->getCart()->customer_id = $this->pixie->auth->user()->id;
        $customer = $this->pixie->orm->get('User')->where('id',$this->pixie->auth->user()->id)->find();
        $this->getCart()->customer_email = $customer->email;
        $this->getCart()->customer_is_guest = 1;
        $this->getCart()->save();
    }

    /**
     * create order, order_addresses, order_items
     */
    public function placeOrder()
    {
        //$this->pixie->db->get()->execute("BEGIN TRANSACTION");//start transaction

        $order = $this->pixie->orm->get('Order');//set order
        $customer = $this->pixie->orm->get('User')->where('id',$this->pixie->auth->user()->id)->find();
        $order->created_at = date('Y-m-d H:i:s');
        $order->customer_firstname = $customer->username;
        $order->customer_email = $customer->email;
        $order->customer_id = $customer->id;
        $order->payment_method = $this->getCart()->payment_method;
        $order->shipping_method = $this->getCart()->shipping_method;
        $order->status = 'complete';
        $order->save();

        $items = $this->getCartItemsModel()->getAllItems();
        foreach ($items as $item) {
            $orderItems = $this->pixie->orm->get('OrderItems');//set order items
            $orderItems->cart_id = $item->cart_id;
            $orderItems->created_at = date('Y-m-d H:i:s');
            $orderItems->product_id = $item->product_id;
            $orderItems->qty = $item->qty;
            $orderItems->price = $item->price;
            $orderItems->name= $item->name;
            $orderItems->order_id = $order->id;
            $orderItems->save();
        }
        $addresses = array(
            'shipping' => $this->pixie->orm->get('CustomerAddress')->where('and', array('id', '=', $this->getCart()->shipping_address_id), array('customer_id', '=', $this->pixie->auth->user()->id))->find(),
            'billing' => $this->pixie->orm->get('CustomerAddress')->where('and', array('id', '=', $this->getCart()->billing_address_id), array('customer_id', '=', $this->pixie->auth->user()->id))->find()
        );
        foreach ($addresses as $type => $address) {//set order addresses
            $orderAddress = $this->pixie->orm->get('OrderAddress');
            $orderAddress->full_name = $address->full_name;
            $orderAddress->address_line_1 = $address->address_line_1;
            $orderAddress->address_line_2 = $address->address_line_2;
            $orderAddress->city = $address->city;
            $orderAddress->region = $address->region;
            $orderAddress->zip = $address->zip;
            $orderAddress->country_id = $address->country_id;
            $orderAddress->phone = $address->phone;
            $orderAddress->customer_id = $this->pixie->auth->user()->id;
            $orderAddress->address_type = $type;
            $orderAddress->order_id = $order->id;
            $orderAddress->save();
        }
        $this->updateLastStep(self::STEP_ORDER);
        //$this->pixie->db->get()->execute("COMMIT");//end transaction
    }
}