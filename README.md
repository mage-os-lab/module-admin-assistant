# module-admin-assistant

Introducing magento admin assistant, a powerful tool that revolutionizes admin interactions with AI capabilities.

**Why module-admin-assistant?**

This project revolutionizes Magento 2 admin interactions with cutting-edge AI capabilities. The core features include:

- **🚀 AI Chatbot Integration:** Enhance admin experience with an intuitive chatbot UI.
- **💡 Interface Flexibility:** Easily interact with agents, bots, and callbacks through defined interfaces.
- **🔍 Document Embeddings:** Generate AI prompt-enhancing document embeddings effortlessly.
- **🧪 Test Set Creation:** Facilitate evaluation framework testing with automated test set generation.
- **💡 Secure SQL Query Handling:** Safely process and execute SQL queries with built-in safety measures.
- **🔗 Smart Link:** Automatically suggest relative link and redirect users base on their questions with one click.

## How to install

`composer require mage-os/module-admin-assist`

## Configuration

go to stores -> configuration -> advanced -> admin -> AI Assistant

enable this feature, select your LLM provider and put in the LLM host url and/or API credentials

run `bin/magento assistant:train` to load LLM with magento domain knowledge

## How to use

- Click the chatbot icon on left sidebar to open the chatbot UI
- Ask questions
- Get answers
