#!/bin/bash
composer install
mkdir /output
zip -x .gitignore -x start.sh -x composer.* -x todo.md -r /output/Lime_RabbitMQ.zip .