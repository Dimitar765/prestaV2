##

prestashop integration with ecom-zone-api

eComZone Dropshipping - API
documentation

1. Introduction
   Generate API token on platform page: https://dropship.ecomzone.eu/api-key.
   To generate API token, your account must be confirmed by our dropshipping manager.
   If you need help, please contact nemanja@ecomzone.eu or via WhatsApp
   +38670290400
   API token use in request, either as bearer-token or as token parameter.
2. Endpoints
   Use different endpoints based on the action you want to take.
   Catalog endpoint - Use, when you want to see a full product catalog.
   Product endpoint - Use, when you want to see all data/information about a specific
   product.
   Ordering endpoint - Use, when you want to send orders to the platform.
   Order endpoint - Use, when you want to see information about a specific order.
   Rate-limit = 120 requests per minute per IP address
   2.1 CATALOG ENDPOINT
   GET https://dropship.ecomzone.eu/api/catalog
   On this endpoint, we have pagination. By default, it will show 1000 products in
   response.
   By adding the url parameter per_page, you can set the number of products per page in
   response.
   per_page = integer (optional, min = 10, max = 1000)
   Example:https://dropship.ecomzone.eu/api/catalog?per_page=10
   If you set per_page < 10, the system will automatically switch per_page = 10.
   2.1.1. CURL
   2.1.2 RESPONSE
   http status-code
   Response body as json-encoded array. Each array member is data for one
   product.
   Paginated:
   Pagination response data:

- "current_page": 1,
- "next_page_url": "null",
- "path": "https://dropship.ecomzone.eu/api/catalog",
- "per_page": "1000",
- "prev_page_url": null,
- "to": 561,
- "total": 561
  Product_id is part of full_sku, which determines variation of the product(color, size). If
  there is only 1 product_id, it means that product has only 1 variation, but when creating
  an order, you still need to send full_sku(main_sku + product_id).
  2.2 PRODUCT ENDPOINT
  GET https://dropship.ecomzone.eu/api/product/{main_sku}
  {main_sku} = product sku
  Example, in case of Kitchen food processor {2246-911}
  https://dropship.ecomzone.eu/api/product/2246-911
  2.2.1 CURL
  2.2.2 RESPONSE
  http-code
  response-body json-encoded array:
- status: ok or error
- message: error message in case of status = error
- data as json-econded array with detail data about product, product-variations,
  stocks
  2.3 ORDERING ENDPOINT
  POST https://dropship.ecomzone.eu/api/ordering
  request: orders as json-encoded array
  Each array member represents data for specific order with parameters:
- order_index (string or integer) (optional): For indexing specific order in response
- ext_id ( string max 20 char ) (optional): Internal order_id, which is visible on platform
- shop_name (string max 20 char) (optional): Your custom shop_name, which will be
  visible on shipping labels as sender of package
- payment['method'](string 2 char 'cod' or 'pp') (required):Cash On Delivery or Prepaid
- payment['customer_price'] (number integer or decimal) ( required if payment-method =
  'cod'): Amount which is collected from end customer
  //customer_price value needs to be sent in local currency
- customer_data['full_name] (string max 200) (required): Full name and surname of
  customer
- customer_data['email'] (string max 100) (required): Email address of customer
- customer_data['phone_number'] (string max 45) (required): Phone number of
  customer
- customer_data['country'] (string 2 char)(required): Alpha-2 customer country-code
- customer_data['address'] (string max 1000) (required): Customer address
- customer_data['city] (string max 100) (required): Customer city name
- customer_data['post_code'](string max 45)(required): Postal code
- customer_data['comment'] (STRING MAX 1000)(optional): Comment/note for courier
  items: array with data about products in order
- items['full_sku'](string) (required): Full-sku of products
- items['quantity']( number integer )(required): Quantity of single product in order
  2.3.1 CURL
  2.3.2 RESPONSE
  http status-code (200 - if all orders from request has been successfully imported;
  202 - if 1 or more orders from request is not imported)
  response body is in form, json-encoded array:
- status: ok(200), accepted(202) or error(422)
- message:
- data['received] : number of orders in request
- data['imported]: number of successfully imported orders
- index: json-encoded array with status of each order from request(if
  order_index is set in request)
  index['order_index']['status]: ok or error
  index['order_index]['order_id']: If indexed order was successfully imported
  (status = ok), here we return order_id, under which order was imported on
  platform
  index['order_index]['note]: If order was not imported (status = error), here
  we return an error message explaining why it was not imported ( which
  data is incorrect or missing).
  Use “comment”: “test” or “TEST” when sending test orders, so that they don’t
  get confirmed automatically in our system.
  2.4 ORDER ENDPOINT
  GET https://dropship.ecomzone.eu/api/order/{order_id}
  {order_id} = ID of order from eComZone platform
  If the requested order_id does not belong to the user, the response will be
  401-unauthorized.
  2.4.1 CURL
  2.4.2 RESPONSE
  http-code
  response-body contains data about order in form json-encoded array:
- status: ok or error
- data as json-encoded array:
- currency
- order_status: title of current status
- order_created_time: time in format Y-m-d H:i:sa
- last_order_status_changed: time of last status changed, in format Y-m-d H:i:s
- payment_method: cod or pp
- customer_price: value that is collected from customers (cod orders)
- returned_bonus: value of return bonus (if exists)
- order_tracking_number: tracking number
- order_tracking_url: tracking url
- order_details['items_cost']: total cost of products in order
- order_details['tax']: total amount of tax in order (if exists)
- order_details['shipping]: shipping price
- order_details['subtotal']: total value/cost of order
- order_details['bonus]: bonus amount which we deduct from total cost of order (if
  exists)
- order_details['total']: final value/amount of order to pay
