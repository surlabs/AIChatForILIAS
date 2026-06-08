![AIChat](https://github.com/user-attachments/assets/634be0e5-58c3-4b58-a0e9-f4f97b8811d6)

# AI Chat Repository Object Plugin for ILIAS 11

Welcome to the official repository for AI Chat Repository Object Plugin for ILIAS
This Open Source ILIAS Plugin is created and maintained by [SURLABS](https://www.surlabs.com)

## What is AI Chat for ILIAS?

This plugin enhances ILIAS platforms by enabling seamless integration with both online Large Language Models (LLMs) like OpenAI's GPT series, and locally-installed models such as LLaMA. It allows for real-time interaction with these advanced AI models directly within the learning environment, enabling dynamic, AI-driven text generation and assistance. The plugin supports customizable configurations to connect to various API endpoints or local installations, ensuring flexibility and control. This integration not only enriches educational content but also provides learners and educators with powerful tools for automated question-answering, content summarization, and personalized learning experiences.

## Which LLMs are currently supported?

This plugin currently supports the following LLMs:

- [OpenAI](https://openai.com)
  - GPT-5.4  
  - GPT-5.4 mini  
  - GPT-5.4 nano  
  - GPT-4.1
  - GPT-4o  
  - GPT-4o mini

- [Ollama](https://ollama.com/) (Local)
  - Compatible with any model that can be loaded in the Ollama runtime (e.g., LLaMa 3, CodeLLaMa, Mistral, Gemma, etc.)

- [GWDG](https://docs.hpc.gwdg.de/services/saia)
  - GWDG Cloud

## Can I use different API-keys for different Objects?

This plugin has been developed to allow the use of different API-keys for different objects. This means that you can use different API-keys for different chatrooms, and it has also the option at plugin configuration, to set one API-key for all chatrooms, disabling, api key field from each object's configuration.

## Installation & Update

### Software Requirements
- AI Chat requires [PHP](https://php.net) versions 8.3 or higher to work properly on your ILIAS 11 platform
- In case you want to connect with GPT on the cloud, AIChat requires at least one [OpenAI](https://openai.com) GPT API key to work on your ILIAS platform.

### Installation steps
1. Create subdirectories, if necessary for public/Customizing/global/plugins/Services/Repository/RepositoryObject/
2. In public/Customizing/global/plugins/Services/Repository/RepositoryObject/
3. Then, execute:
```bash
git clone https://github.com/surlabs/AIChatForILIAS.git ./AIChat
cd AIChat
git checkout release_11
```
3. AI Chat uses the ILIAS composer autoloader functionality so, after installing or updating the plugin, ensure you run on the ILIAS root folder
```bash
composer du
```
***
**Please ensure you don't ignore plugins on composer.json**
***
