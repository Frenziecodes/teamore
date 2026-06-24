=== AccountX - Teams & Subaccounts for WooCommerce ===
Contributors: 38zo
Tags: woocommerce, b2b, subaccounts, customer accounts, teams
Requires at least: 6.2
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Turn WooCommerce customer accounts into team accounts.

== Description ==

AccountX - Teams & Subaccounts for WooCommerce is a lightweight MVP foundation for stores that need parent customer accounts to create and manage subaccounts.

Parent customers can create normal WordPress users with the WooCommerce customer role, view those subaccounts in My Account, manage basic details, delete subaccounts, view subaccount orders, and optionally switch into a subaccount session.

The plugin is intentionally simple and stable. Advanced B2B features such as permissions, spending limits, approvals, quotes, invoices, branch hierarchies, and API integrations are outside the MVP scope.

== Features ==

* Parent customers can create, edit, and delete subaccounts.
* Subaccounts are normal WordPress users with the customer role.
* Configurable subaccount limit, defaulting to 10.
* Use 0 for unlimited subaccounts.
* Configure whether admins, parent customers, or both can create subaccounts.
* Multi-User Mode and Sub-User Mode labels.
* Configurable subaccount display name format.
* Parent accounts can see orders placed by subaccounts.
* My Account orders display who placed each order.
* Optional AccountX information on WooCommerce order pages, WooCommerce order lists, and the WordPress users list.
* Optional parent-to-subaccount user switching.
* Minimal settings page under WooCommerce > AccountX.

== Installation ==

1. Upload the `accountx` folder to `/wp-content/plugins/`.
2. Activate AccountX from the Plugins screen.
3. Go to WooCommerce > AccountX to review settings.
4. If the My Account endpoint does not appear, visit Settings > Permalinks once to refresh rewrite rules.

== Frequently Asked Questions ==

= Does AccountX create a custom user type? =

No. Subaccounts are regular WordPress users with the WooCommerce customer role.

= Can subaccounts manage other subaccounts? =

No. Only parent accounts can manage their own subaccounts.

== Screenshots ==

1. Accountx settings Page.

== Upgrade Notice ==

= 1.0.0 =

Initial release.

== Changelog ==

= 1.0.0 =
* Initial MVP release.
