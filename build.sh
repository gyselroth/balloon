#!/bin/bash

OPT_DIR='./build'
OPT_COMPOSER=0
OPT_NPM=0
OPT_TEST=0
OPT_PHPCS_FIX=0
OPT_PHPSTAN=0
OPT_APIDOC=0
OPT_VERSION=0
OPT_PACKAGE=0
OPT_EXCLUDE_APPS=0
OPT_SOURCE=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)
OPT_IGNORE=0

version=$(cat $OPT_SOURCE/VERSION)
ts=$(date +%Y%m%d)
cd $OPT_SOURCE

showHelp() {
cat <<-END
Balloon build tool $version

  -a --apidoc
    Creates the API documentation guide, available under ./doc

  -c --composer
    Install and update all php requirements (getcomposer.org)

  -e --exclude-apps
    Per default alls apps within src/apps will be integrated into the build

  -d --dep
    Installs all requiremenets, equal to "--composer --npm"

  -f --full-build
    Executes a full build, equal to "--dep --test --apidoc --php-cs-fixer"

  -h --help
    Shows this message

  -i --ignore
    Ignore any error

  -n --npm
    Install npm dependencies

  -p --package tar,deb [DEFAULT: tar,deb]
    This generates a tar archive of a complete build, equal to "--dep --test --apidoc --php-cs-fixer"
    and creates a package with the result

  -P --php-cs-fixer
    Executes php-cs-fixer for PSR-1/PSR-2 code style

  -s --source SOURCE_FOLDER [DEFAULT: ./]
    The folder of the application itself, usually the folder where this build scrip lies

  -S --phpstan
    Executes phpstan on src/ and tests/ directories

  -t --test
    Executes the testing library (phpunit checks)

  -v --version
    Shows the version
END
exit 127
}

if [[ $# -eq 0 ]]; then
    showHelp
fi

while [[ $# -gt 0 ]]; do
key="$1"
case $key in
    -h|--help)
    showHelp
    ;;
    -p|--package)
    if [[ "$2" == '' || "${2:0:1}" == '-' ]]; then
      OPT_PACKAGE="tar,deb"
    else
      OPT_PACKAGE="$2"
    fi
    shift

    ;;
    -f|--full-build)
    OPT_COMPOSER=1
    OPT_NPM=1
    OPT_APIDOC=1
    OPT_TEST=1
    #OPT_PHPSTAN=1
    OPT_PHPCS_FIX=1
    ;;
    -P|--php-cs-fixer)
    OPT_PHPCS_FIX=1
    ;;
    -e|--exclude-apps)
    OPT_EXCLUDE_APPS=1
    ;;
    -d|--dep)
    OPT_COMPOSER=1
    OPT_NPM=1
    ;;
    -c|--composer)
    OPT_COMPOSER=1
    ;;
    -n|--npm)
    OPT_NPM=1
    ;;
    -t|--test)
    OPT_TEST=1
    ;;
    -i|--ignore)
    OPT_IGNORE=1
    ;;
    -a|--apidoc)
    OPT_APIDOC=1
    ;;
    -s|--source)
    OPT_SOURCE="$2"
    shift
    ;;
    -S|--phpstan)
    OPT_PHPSTAN=1
    ;;
    -v|--version)
    OPT_VERSION=1
    ;;
    *)
    showHelp
    ;;
esac
shift
done

if [ $OPT_VERSION -eq 1 ]; then
    echo $version
    exit 127
fi

echo "Balloon build tool $version"
echo

if [ $OPT_COMPOSER -eq 1 ]; then
    echo "[TASK] Execute composer"
    php composer.phar update
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "composer requirements not met, abort build."
        exit 127
    fi
    if [ $OPT_EXCLUDE_APPS -eq 0 ]; then
        for app in `ls $OPT_SOURCE/src/app`; do
            if [ -e $OPT_SOURCE/src/app/$app/composer.json ]; then
                cd $OPT_SOURCE/src/app/$app
                php composer.phar update
                if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
                    echo "composer requirements for app $app not met, abort build."
                    cd $OPT_SOURCE
                    exit 127
                fi
                cd $OPT_SOURCE
            fi
        done
    fi
fi

if [ $OPT_NPM -eq 1 ]; then
    echo "[TASK] Execute npm"
    npm update
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "npm requirements not met, abort build."
        exit 127
    fi
fi

if [ $OPT_PHPCS_FIX -eq 1 ]; then
    echo "[TASK] Execute php-cs-fixer"
    php ./vendor/friendsofphp/php-cs-fixer/php-cs-fixer fix --allow-risky yes src/ --rules="@PSR1,@PSR2,declare_strict_types,new_with_braces,no_empty_phpdoc"
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "php-cs-fixer failed, abort build."
        exit 127
    fi
fi

if [ $OPT_APIDOC -eq 1 ]; then
    echo "[TASK] Execute apidoc"
    input="-i $OPT_SOURCE/src/lib/Balloon/Api"
    if [ $OPT_EXCLUDE_APPS -eq 0 ]; then
        for app in `ls $OPT_SOURCE/src/app`; do
            if [ -e $OPT_SOURCE/src/app/$app/src/lib/Api ]; then
                input="$input -i $OPT_SOURCE/src/app/$app/src/lib/Api"
            fi
        done
    fi

    apidoc $input -o $OPT_SOURCE/doc
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "failed generate apidoc, abort build."
        exit 127
    fi
fi

if [ $OPT_TEST -eq 1 ]; then
    echo "[TASK] Execute phpunit"
    ./vendor/phpunit/phpunit/phpunit --stderr --debug --bootstrap tests/Unit/Bootstrap.php tests/Unit
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "unit testing failed, abort build"
        exit 127
    fi

    if [ $OPT_EXCLUDE_APPS -eq 0 ]; then
        for app in `ls src/app`; do
            if [ -e $OPT_SOURCE/src/app/$app/tests ]; then
                ./vendor/phpunit/phpunit/phpunit --bootstrap tests/Bootstrap.php $OPT_SOURCE/src/app/$app/tests
                if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
                    echo "unit testing for app $app failed, abort build"
                    exit 127
                fi
            fi
        done
    fi
fi

if [ $OPT_PHPSTAN -eq 1 ]; then
    echo "[TASK] Execute phpstan"
    php ./vendor/bin/phpstan analyse src tests
    if [[ $? -ne 0 && $OPT_IGNORE -eq 0 ]]; then
        echo "phpstan failed, abort build."
        exit 127
    fi
fi

function generateDebianChangelog {
    v=
    stable="stable"
    author=
    date=
    changes=
    rm $OPT_SOURCE/build/DEBIAN/changelog

    while read l; do
        if [ "${l:0:2}" == "##" ]; then
            if [ "$v" != "" ]; then
                echo "balloon-server ($v) $stable; urgency=low" >> $OPT_SOURCE/build/DEBIAN/changelog
                echo -e "$changes" >> $OPT_SOURCE/build/DEBIAN/changelog
                echo >>  $OPT_SOURCE/build/DEBIAN/changelog
                echo " -- $author  $date" >> $OPT_SOURCE/build/DEBIAN/changelog
                echo >>  $OPT_SOURCE/build/DEBIAN/changelog
                v=
                stable="stable"
                author=
                date=
                changes=
            fi

            v=${l:3}
            if [[ "$v" == *"RC"* ]]; then
                stable="unstable"
            elif [[ "$v" == *"BETA"* ]]; then
                stable="unstable"
            elif [[ "$v" == *"ALPHA"* ]]; then
                stable="unstable"
            elif [[ "$v" == *"dev"* ]]; then
                stable="unstable"
            fi
        elif [ "${l:0:5}" == "***Ma" ]; then
            p1=$(echo $l | cut -d '>' -f1)
            p2=$(echo $l | cut -d '>' -f2)
            author="${p1:18}>"
            date=${p2:12}
            date=$(date -d"$date" +'%a, %d %b %Y %H:%M:%S %z')
        elif [ "${l:0:2}" == "* " ]; then
            changes="  $changes\n  $l"
        fi
    done < CHANGELOG.md
}

function buildDeb {
    mkdir -p $OPT_SOURCE/build/DEBIAN
    cp $OPT_SOURCE/packaging/debian/control-$1 $OPT_SOURCE/build/DEBIAN/control
    sed -i s/'{version}'/$version/g $OPT_SOURCE/build/DEBIAN/control
    generateDebianChangelog
    mkdir -p $OPT_SOURCE/build/usr/share/balloon-server
    mkdir -p $OPT_SOURCE/build/etc/balloon
    mkdir -p $OPT_SOURCE/build/var/log/balloon
    #cp -Rp $OPT_SOURCE/composer.* $OPT_SOURCE/build/usr/share/balloon-server
    #cp -Rp $OPT_SOURCE/package.json $OPT_SOURCE/build/usr/share/balloon-server
    #cp -Rp $OPT_SOURCE/{vendor,src,doc} $OPT_SOURCE/build/usr/share/balloon-server
    dpkg-deb --build build
    mkdir $OPT_SOURCE/dist
    mv build.deb $OPT_SOURCE/dist/balloon-server-$1-$version.deb
}

if [[ ! "$OPT_PACKAGE" == "0" ]]; then
    for i in $(echo $OPT_PACKAGE | tr "," "\n"); do
        if [ "$i" == "tar" ]; then
          echo "[TASK] Create tar archive"
          rm -rfv $OPT_SOURCE/*.tar.gz
          mkdir build
          cp -Rp $OPT_SOURCE/* $OPT_SOURCE/build
          rm -rf $OPT_SOURCE/build/log/*
          find $OPT_SOURCE/build -name .svn -exec rm -rf {} \;
          archive=balloon-build-$version.tar.gz
          tar -cvzf $OPT_SOURCE/$archive -C $OPT_SOURCE/build .
          rm -rf $OPT_SOURCE/build

          checksum=$(md5sum $OPT_SOURCE/$archive | cut -d' ' -f1)
          echo
          echo "package available at $OPT_SOURCE/$archive"
          echo "MD5 CHECKSUM: $checksum"
        fi

        if [ "$i" == "deb" ]; then
            echo "[TASK] Create deb package"
            buildDeb light
            buildDeb full
        fi
    done
fi
