=== WooCommerce Cost of Shipping ===
Contributors:      TheRiteSites   
Donate link:       https://www.theritesites.com/plugins/woocommerce-cost-of-shipping  
Tags:              cost of shipping, shipping cost, WooCommerce, net profit, shipstation
Requires at least: 5.2
Tested up to:      6.5
Requires PHP:      7.0
Stable tag:        trunk
License:           GPLv2  
License URI:       http://www.gnu.org/licenses/gpl-2.0.html  

Allows the association of the cost of shipping to WooCommerce orders.

== Description ==

Tracking and analyzing the true profitability of your WooCommerce orders is not possible if you’re not including the cost of shipping. Most sites use a “Cost of Goods” plugin to analyze profit (sales less cost of goods), but this is not a true measure of profitability. The cost of shipping orders is significant, and in many businesses, it can be even more expensive than the cost of goods, so including the cost of shipping in your analysis is critical to running a successful eCommerce business.

**NEW!** Using WooCommerce Services to buy your shipping labels? Well we have just made new functionality that allows amount spent on shipping to be imported directly into the order! The plugin will automatically do this if the labels have been purchased before the order is marked complete, or there is a manual import button found neatly on the right sidebar while editing orders. Whether you purchase one, multipls labels, or refund labels, we report the Cost of Shipping directly to you!

After installing this plugin you’ll be able to manually add or adjust the cost of shipping for each order on the WooCommerce Edit Order page. **Better yet**, if you use ShipStation then the cost of shipping for each order will be automatically imported as your orders are shipped. If you ship multi-package orders, the total cost of all packages will be imported. If you void a ShipStation shipment and then reship (change shipping method, address, etc.), the new cost of shipping will be imported. It’s all automated and you’re going to love it!

Once you start tracking the cost of shipping, you’ll want to use our [WooCommerce Net Profit](https://www.theritesites.com/plugins/woocommerce-net-profit) plugin that will give you beautiful, functional reports to fully analyze your profitability.

**Do you use your income statement to track the cost of shipping?**
Most eCommerce businesses do, but this just gives you a high level view of the overall profitability of your business. What’s missing is the ability to determine true profitability at the individual order level.

For example: 
* If you ship out of California, how does your profit on an order shipped to a customer in California compared to the same order shipped to a customer in New York?
* How does your profit on an order that weighs 2 pounds compare to orders that weigh 5, 10 or 20 pounds?
* If you offer free shipping on orders over a certain dollar amount ($50, $75, etc.), how does the profit on those orders compare to the profit on orders that you are charging a shipping fee?
* How does your profit on a $25 order compare to your profit on a $50, $100 or $200 order?

Only after you have the cost of shipping data at the order level can you make these comparisons (and more), and then make smart adjustments to the way you offer your products to maximize profitability. 

== Installation ==

=== Manual Installation ===

1. Upload the entire `/woo-cost-of-shipping` directory to the `/wp-content/plugins/` directory.
2. Activate WooCommerce Cost of Shipping through the 'Plugins' menu in WordPress.
3. Enjoy the easy editting of the cost of shipping in the edit order screen, below the order total.

== Frequently Asked Questions ==
= Cost of Shipping from ShipStation is not being saved or imported =
There is a common issue we have found in the ShipStation Marketplace Configuration. Ensure your store does not have a redirect from http:// to https:// and also that the Marketplace on ShipStation matches the exact url and protocol. [Read more here](https://www.theritesites.com/docs/shipstation-not-importing-cost/)
= The "Import shipping cost" button is not importing the data! =
If you are seeing the "Import shipping cost" or "Re-import shipping cost" button, that means you have the WooCommerce Services plugin active. The only shipping costs that will be imported are from that plugin, no other integrations will import shipping costs using this button. (e.g. shipstation)
= Are there any integrations? =
Currently, we support the [ShipStation platform](https://woocommerce.com/products/shipstation-integration/), and the [WooCommerce Services](https://wordpress.org/plugins/woocommerce-services/), and are looking to extend that.
= Is there any reporting tools to go along with this data? =
We currently offer a [reporting plugin](https://www.theritesites.com/plugins/woocommerce-net-profit) that reports on your true cost of shipping and more at our website!

== Screenshots ==
1. A new field is added underneath the order items block, underneath the "Total" row, along with an edit button.
2. Shows the JavaScript popup that allows you to manually edit the cost of shipping for this order.
3. Shows the meta box on the right to import Cost of Shipping, the order note put in, and the Cost of Shipping
4. Shows expanded meta box and pro tip about using the plugin to its potential
5. Shows the new interface if WooCommerce Services plugin is activated

== Changelog ==

= 1.5.4 =
- Fixed: WooCommerce Services incompatibility caused crash in some stores on the order and analytics page.

= 1.5.3 =
- Fixed: Changed how conditional was checked to prevent debug potentially crashing administrative screen.

= 1.5.2 =
- Fixed: Changed how conditional was checked to prevent warning output in debug log.

= 1.5.1 = 
- Fixed: WooCommerce admin page changes prevented javascript enqueueing.

= 1.5.0 =
- WooCommerce High Performance Order Storage (HPOS) update

= 1.4.2 =
- Security update
- Tested on recent WordPress and WooCommerce

= 1.4.1 =
- Security update

= 1.4.0 =
- Tweak: New function to work with the new more dynamic version of WooCommerce Net Profit.
- Tweak: Changed some casting to float, to floatval() calls.
- Tweak: Changed debug location to a more universal file for debugging TRS plugins.

= 1.3.1 =
- Fixed: links mispelled WooCommerce and are corrected to have the correct links.

= 1.3.0 =
- New: Order meta to store history of Cost of Shipping after the first store (no redundant data).
- Tweak: Changed how Re-Import works when labels either previously existed or not when interacting with WooCommerce Services and Stamps labels.
- Tweak: Disabled buttons while waiting on manual Cost of Shipping change.
- Fixed: Matched WooCommerce Order table spacing for Total Cost of Shipping Width.

= 1.2.0 =
- New: Added automated imports of WooCommerce Services Shipping Labels Cost of Shipping!
- Tweak: Changed how tooltip chooses to appear on different browsers to match Cost of Goods for WooCommerce's approach (CSS)
- Fixed: Bug where previous cost of shipping would not appear in manual entry tooltip

= 1.1.0 =
- New: Added Order Note when Cost of Shipping is changed
- Tweak: Isolated JavaScript into its own file rather than enqueued into the footer

= 1.0.1 =
- Tweak: Changed how WooCommerce active check is handled to correctly show error message and deactivate plugin when WooCommerce not found

= 1.0.0 =
- First release
- Reads in manual entries for cost of shipping
- Automatically reads in the cost of shipping on an order if you currently use the ShipStation plugin and platform

== Upgrade Notice ==

= 1.4.1 =
- Security update

= 1.4.0 =
- New capabilities to integrate with reporting

= 1.2.0 =
- New capabilities to integrate with WooCommerce Services

= 1.1.0 =
- New JavaScript file
- New Order Note data when Cost of Shipping is updated

= 1.0.2 =
- Update repository structure
- Tested new versions of wordpress and woocommerce

= 1.0.1 =
- Updated README

= 1.0.0 =
- First Release
