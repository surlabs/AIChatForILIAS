# AI Chat plugin for ILIAS

Welcome to the official repository for [AI Chat plugin for ILIAS](https://www.surlabs.es).
This plugin is created and maintained by Daniel Cazalla and Jesús Copado, founders of [SURLABS](https://www.surlabs.es) and is designed to work with ILIAS 8.0 and above.

## What is AI Chat for ILIAS?

This plugin adds a new repository object to your platform, where chatrooms with an LLM can be held by the users. Allowing users to interact with the LLM in a natural way within the ILIAS platform.

## Which LLMs are currently supported?

This plugin currently supports the following LLMs:
- [OpenAI](https://openai.com) GPT-4

## Can I use different API-keys for different Objects?

This plugin has been developed to allow the use of different API-keys for different objects. This means that you can use different API-keys for different chatrooms, and it has also the option at plugin configuration, to set one API-key for all chatrooms, disabling, api key field from each object's configuration.

## Installation & Update

### Software Requirements
- AI Chat requires [PHP](https://php.net) version 7.4 to work properly on your ILIAS 7 platform
- AI Chat requires at least one [OpenAI](https://openai.com) GPT API key to work properly on your ILIAS platform

### Installation steps
1. Create subdirectories, if necessary for Customizing/global/plugins/Services/Repository/RepositoryObject/
2. In Customizing/global/plugins/Services/Repository/RepositoryObject/ 
3. Then, execute:
```bash
git clone https://github.com/surlabs/AIChatForILIAS.git ./AIChat
cd AIChat
git checkout ilias7_dev
```
3. AI Chat uses the ILIAS composer autoloader functionality so, after installing or update the plugin, ensure you run on the ILIAS root folder
```bash
composer du
php setup/setup.php update
```
***
**Please ensure you don't ignore plugins on composer.json**
***
