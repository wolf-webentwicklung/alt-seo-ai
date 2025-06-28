=== AltSEO AI Plus ===
Contributors: wolfwebentwicklung
Donate link: https://www.wolfwebentwicklung.de/donate/
Tags: seo, alt text, images, ai, openai, gpt
Requires at least: 5.2
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically generates image alt text with custom keywords using OpenAI's advanced AI models.

== Description ==

AltSEO AI Plus is a powerful WordPress plugin that leverages OpenAI's advanced AI models to automatically generate optimized alt text for your images based on custom keywords.

**Key Features:**

* Automatically generate SEO-focused keywords for your content
* Generate alt text for images using OpenAI's vision models
* Customize the number of keywords to generate
* Support for bulk generation of keywords and alt text
* Support for multiple languages - automatically detects content language
* Modern UI with Vue.js interface

**How It Works:**

1. The plugin analyzes your content and generates relevant SEO keywords
2. When you add images, it uses these keywords plus AI vision models to create optimized alt text
3. All generated content is tailored to match the detected language of your post

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/altseo-ai-plus` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to Media > AltSeo-AI Plus to configure your OpenAI API key and settings

== Frequently Asked Questions ==

= Do I need an OpenAI API key? =

Yes, you need an OpenAI API key to use this plugin. You can obtain one from [OpenAI's website](https://platform.openai.com/).

= Which OpenAI models are supported? =

The plugin supports a range of OpenAI models for text generation (like GPT-3.5 Turbo, GPT-4) and vision models for image analysis (like GPT-4o-mini).

= Does it support languages other than English? =

Yes! The plugin automatically detects the language of your content and generates keywords and alt text in the same language.

== Changelog ==

= 1.0.1 =
* Improved security with proper nonce checks
* Enhanced input sanitization and validation
* Better error handling and logging
* Code refactoring for WordPress coding standards compliance
* Added support for more OpenAI vision models

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.1 =
This update includes important security enhancements and improved error handling. All users should upgrade.

== Privacy Policy ==

AltSEO AI Plus sends your content and images to OpenAI's API for processing. Please ensure this complies with your privacy policy and GDPR requirements if applicable.

For more information on how OpenAI handles data, please see their [privacy policy](https://openai.com/privacy/).
