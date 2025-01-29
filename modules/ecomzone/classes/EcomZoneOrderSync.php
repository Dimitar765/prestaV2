<?php

class EcomZoneOrderSync
{
    private $client;

    public function __construct()
    {
        $this->client = new EcomZoneClient();
    }

    public function syncOrder($orderId)
    {
        try {
            EcomZoneLogger::log("Starting order sync", 'INFO', ['order_id' => $orderId]);
            
            $order = new Order($orderId);
            $customer = new Customer($order->id_customer);
            $address = new Address($order->id_address_delivery);

            $orderData = $this->prepareOrderData($order, $customer, $address);
            $result = $this->client->createOrder($orderData);
            
            EcomZoneLogger::log("Order sync completed", 'INFO', [
                'order_id' => $orderId,
                'result' => $result
            ]);
            
            return $result;
        } catch (Exception $e) {
            EcomZoneLogger::log("Order sync failed", 'ERROR', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function prepareOrderData($order, $customer, $address)
    {
        return [
            'order_index' => $order->id,
            'ext_id' => $order->reference,
            'payment' => [
                'method' => $this->getPaymentMethod($order),
                'customer_price' => $order->total_paid
            ],
            'customer_data' => [
                'full_name' => $customer->firstname . ' ' . $customer->lastname,
                'email' => $customer->email,
                'phone_number' => $address->phone,
                'country' => Country::getIsoById($address->id_country),
                'address' => $address->address1 . ' ' . $address->address2,
                'city' => $address->city,
                'post_code' => $address->postcode
            ],
            'items' => $this->getOrderItems($order)
        ];
    }

    private function getPaymentMethod($order)
    {
        // Map PrestaShop payment modules to eComZone payment methods
        return $order->module === 'cashondelivery' ? 'cod' : 'pp';
    }

    private function getOrderItems($order)
    {
        $items = [];
        $products = $order->getProducts();

        foreach ($products as $product) {
            $items[] = [
                'full_sku' => $product['reference'],
                'quantity' => $product['product_quantity']
            ];
        }

        return $items;
    }
} 