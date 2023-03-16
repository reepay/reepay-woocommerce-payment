#!/bin/bash

standard='--standard=./ruleset.xml'
path='./includes/*.php ./includes/**/*.php ./templates/**/*.php ./*.php'
extra='--cache --colors -p -s' #remove colors if your terminal doesn't support them

if [ "$1" == "-full" ]; then
  php ./vendor/bin/phpcs $standard $path $extra
elif [ "$1" == '-fix' ]; then
  php ./vendor/bin/phpcbf $standard $path $extra
else
  php ./vendor/bin/phpcs --report=summary $standard $path $extra
fi

read