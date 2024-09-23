# AI Chat Repository Object Plugin for ILIAS

Welcome to the official repository for AI Chat Repository Object Plugin for ILIAS
This Open Source ILIAS Plugin is created and maintained by [SURLABS](https://www.surlabs.com)

## What is AI Chat for ILIAS?

This plugin enhances ILIAS platforms by enabling seamless integration with both online Large Language Models (LLMs) like OpenAI's GPT series, and locally-installed models such as LLaMA. It allows for real-time interaction with these advanced AI models directly within the learning environment, enabling dynamic, AI-driven text generation and assistance. The plugin supports customizable configurations to connect to various API endpoints or local installations, ensuring flexibility and control. This integration not only enriches educational content but also provides learners and educators with powerful tools for automated question-answering, content summarization, and personalized learning experiences.

## Which LLMs are currently supported?

This plugin currently supports the following LLMs:
- [OpenAI](https://openai.com) GPT-4o
- [OpenAI](https://openai.com) GPT-4o mini
- [OpenAI](https://openai.com) GPT-4 Turbo
- [OpenAI](https://openai.com) GPT-4
- [OpenAI](https://openai.com) GPT-3.5 Turbo
- [Meta](https://www.llama.com/) (Local) LLaMa 3.1 70b Instruct
- [Meta](https://www.llama.com/) (Local) Codellama 70b 
- [Meta](https://www.llama.com/) (Local) LLaMa 3.1 8b Instruct

## Can I use different API-keys for different Objects?

This plugin has been developed to allow the use of different API-keys for different objects. This means that you can use different API-keys for different chatrooms, and it has also the option at plugin configuration, to set one API-key for all chatrooms, disabling, api key field from each object's configuration.

## Installation & Update

### Software Requirements
- AI Chat requires [PHP](https://php.net) versions 7.4 or 8.0 to work properly on your ILIAS 8 platform
- In case you want to connect with GPT on the cloud, AIChat requires at least one [OpenAI](https://openai.com) GPT API key to work on your ILIAS platform.

### Installation steps
1. Create subdirectories, if necessary for Customizing/global/plugins/Services/Repository/RepositoryObject/
2. In Customizing/global/plugins/Services/Repository/RepositoryObject/ 
3. Then, execute:
```bash
git clone https://github.com/surlabs/AIChatForILIAS.git ./AIChat
cd AIChat
git checkout ilias8
```
3. AI Chat uses the ILIAS composer autoloader functionality so, after installing or update the plugin, ensure you run on the ILIAS root folder
```bash
composer du
php setup/setup.php update
```
***
**Please ensure you don't ignore plugins on composer.json**
***
