# WP Multisite Internal SSO

A WordPress plugin that enables Single Sign-On (SSO) for users across multiple sites in a WordPress Multisite network. This plugin automatically logs in users from a primary site to a secondary site within the same network, facilitating seamless access between sites without requiring reauthentication.

Currently, this works in all directions, users from Site 2 have access to Site 1.

## Features

- **Automatic login**: Automatically logs in users from one site in a WordPress multisite network to another site.
- **Multisite Support**: Works across multiple sites in a WordPress Multisite network.
- **Customizable primary and secondary sites**: Supports defining primary and secondary sites for SSO operations.
- **User session management**: Handles user sessions across sites and ensures consistency in user authentication.
- **Logout functionality**: Allows users to log out from all connected sites with a single click.
- **Clear Cookies Option**: Provides an option to clear authentication cookies.

## Installation

1. Download the plugin files or clone the repository to your WordPress installation's `wp-content/plugins` directory.
2. Go to the WordPress Admin Dashboard.
3. Navigate to `Plugins > Installed Plugins`.
4. Find **WP Multisite Internal SSO** in the list and click **Activate**.

### Requirements

- WordPress 4.7 or greater.
- A WordPress Multisite network must be set up.
- The plugin operates with **Network Activation**.

## Configuration

- **Primary Site**: This is the main site in your WordPress Multisite network, which holds the user accounts. Set this in the plugin settings for the primary site.
- **Secondary Site**: Sites where users will be logged in automatically if they are authenticated on the primary site. Set this in the plugin settings for the primary site.

The plugin is designed to run seamlessly across the multisite network with no additional configuration required after installation. However, the following customization options are available:


### Token and Cookie Management

The plugin uses tokens to verify users during the SSO process. These tokens are generated on the primary site and verified on the secondary site to ensure secure login sessions.

## Usage

### SSO Flow

1. **Login**: The user can log into either site and then be logged into the other automatically
3. **Token Verification**: The plugin generates a unique token on the primary site, which is sent to the secondary site. The secondary site verifies the token and logs the user in automatically.

### Logout

Users can log out from all sites in the network by clicking the **Logout** button provided by the plugin. This will terminate their session across all sites in the multisite network.

### Clear Cookies

The plugin provides an option to clear authentication cookies when in dev mode. Clicking the **Clear Cookies** link will remove all login-related cookies, forcing the user to reauthenticate.

## Hooks

- **`wp_body_open`**: Displays login/logout status and provides logout and clear cookies options in the body of each page.
- **`init`**: Initiates the SSO logic when the plugin is loaded.
- **`template_redirect`**: Checks and handles the SSO logic for both primary and secondary sites.

## Developer Notes

- **Debugging**: You can enable debugging by setting `WP_DEBUG` and `WP_DEBUG_LOG` to `true` in the `wp-config.php` file. This will log messages to an sso-debug.log. The file must be created first.
- **Customizing Token Generation**: The token generation method is based on the `AUTH_SALT` constant. You can adjust the method of token creation or add additional security measures if needed.

## Security

- **Token Expiry**: Tokens are valid for 5 minutes. Any token older than this will be considered expired and rejected.
- **Secure Authentication Cookies**: Authentication cookies are cleared across all sites to ensure a secure logout process.

## Troubleshooting

### 1. **Users Not Being Logged In**

- Ensure the **primary site** and **secondary site** URLs are correctly defined in the plugin.
- Check that the primary site user exists on the secondary site. Users must be present on both sites for SSO to work.
- Verify that the `AUTH_SALT` constant is correctly defined in your `wp-config.php`.

### 2. **SSO Token Issues**

- If you receive an "Invalid or expired token" error, it is likely the token has expired or is incorrect. Verify the token generation and time settings.
- Ensure that the user is logged in on the primary site before navigating to the secondary site.

### 3. **Redirect Loop**

- If you experience a redirect loop, it may be due to cookie mismanagement or an incorrect return URL. Ensure cookies are cleared when necessary and verify URL settings.

## License

This plugin is licensed under the GPL v2. See the [LICENSE](LICENSE) file for more details.

## Author

**Author**: 9ete  
**Author URI**: [https://petelower.com](https://petelower.com)

## Contributing

Contributions are welcome! Please fork this repository and submit a pull request. Ensure your code follows WordPress coding standards and includes appropriate tests.

## Changelog

### Version 0.0.7
- Initial release with basic SSO functionality.
- Added logout button and cookie clearing options.

---

*Note*: This plugin has been tested on WordPress 4.7+ multisite installations. It is always recommended to test on a staging site before using on a live environment.
