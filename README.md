# Wingu WordPress plugin

Wingu plugin allows you to seamlessly link content you have created on Wordpress Platform to Triggers you manage through Wingu Proximity Platform.

## Installation

Installing the plugin directly through the WordPress **Plugins** screen is **recommended**.

You can also download wingu.zip file from Releases tab in the plugin's repository on GitHub and then upload it to Your WordPress site through **Plugins** tab of your WP Admin Panel.

It is also possible to clone the plugin's repository on GitHub and then manually invoke command `composer install`, inside newly created directory with cloned source files, to install all its dependencies.
 
How you install the plugin is completely up to you. At the end of installation process, you will be required to activate the plugin.

Then, only after you enter valid **Wingu API key** in **Settings** -> **Wingu**, you will be able to fully benefit from the plugin.

## Configuration 
You can access plugin's settings by selecting **Settings** -> **Wingu** submenu.
You can decide whether you want to, by default, send to Wingu Triggers whole posts/pages or only excerpts of those. You can also add linkback text that will appear at the end of your content. 

You can override those settings on each page/post view.

## Adding content to your Wingu Trigger - step by step

Assuming that you successfully installed and configured the plugin, to send your WordPress content to Wingu Trigger you have to:
1. Open existing post or create new one
1. After you're done editing its content, locate Wingu Metabox, if it's not there check FAQ below
1. Choose whether you want to send to Wingu Trigger post/page content or excerpt
1. Choose whether you want to add linkback that you configured in **Settings** at the end of the content
1. Choose one of the options from select box, either:
* do nothing
* update content from WordPress on Wingu Platform - both WP and Wingu Platform will have same updated copy of your content
* Create new Wingu Content and link to Trigger - Most basic option, just choose Triggers you want to attach your content to
* Add WP Content to existing Wingu Content - It will add post/page content at the beggining of Wingu Deck you select

When everything is set you can click **Publish** or **Update**
## Frequently Asked Questions

### How do I obtain API Key that I need to enter in plugin's settings?
You can receive one by becoming a subscriber at [Wingu Portal](https://www.wingu-portal.de/en/register) site.

### Where can I link content to Wingu Triggers?
You can do so either in PostPage create/edit view or in Settings -> Wingu screen

### I do not see anything related to Wingu on Post/Page editor view
You may have to enable Wingu Metabox by clicking **Screen Options** in the upper right corner of those screens and making sure that checkbox located left of **Wingu** is checked.
