# AI Chat plugin for ILIAS

Welcome to the official repository for [AI Chat plugin for ILIAS](https://www.surlabs.es).
This plugin is created and maintained by [SURLABS](https://www.surlabs.es) and is designed to work with ILIAS 8.0 and above.

## What is AI Chat for ILIAS?

This plugin adds a new repository object to your platform, where chatrooms with an LLM are created and saved. Allowing users to interact with the LLM in a natural way within the ILIAS platform.

## Which LLMs are currently supported?

This plugin currently supports the following LLMs:
- [OpenAI](https://openai.com) GPT-4

## Installation & Update

### Software Requirements
- AI Chat requires [PHP](https://php.net) version 8.0 to work properly on your ILIAS 8 platform
- AI Chat requires at least one [OpenAI](https://openai.com) GPT API key to work properly on your ILIAS platform

### Installation steps
1. Create subdirectories, if necessary for Customizing/global/plugins/Services/Repository/RepositoryObject/
2. In Customizing/global/plugins/Services/Repository/RepositoryObject/ 
3. Then, execute:
```bash
git clone https://github.com/surlabs/AIChat.git
cd AIChat
git checkout ilias8
```
3. STACK uses the ILIAS composer autoloader functionality so, after installing or update the plugin, ensure you run on the ILIAS root folder
```bash
composer du
php setup/setup.php update
```
***
**Please ensure you don't ignore plugins on composer.json**
***