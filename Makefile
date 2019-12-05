SHELL=/bin/bash

# DIRECTORIES
BASE_DIR = .
SRC_DIR = $(BASE_DIR)/src
CORE_DIR = $(SRC_DIR)/lib
CORE_API_DIR = $(SRC_DIR)/app
CONFIG_DIR = $(BASE_DIR)/config
VENDOR_DIR = $(BASE_DIR)/vendor
TESTS_DIR = $(BASE_DIR)/tests
UNITTESTS_DIR = $(TESTS_DIR)/Unit
DIST_DIR = $(BASE_DIR)/dist
LOG_DIR = $(BASE_DIR)/log
BUILD_DIR = $(BASE_DIR)/build
INSTALL_PREFIX = "/"

# VERSION
ifeq ($(VERSION),)
VERSION := "0.0.1"
endif

# PACKAGES
TAR = $(DIST_DIR)/balloon-$(VERSION).tar.gz

# PHP BINARY
PHP_BIN = php
# COMPOSER STUFF
COMPOSER_BIN = composer
COMPOSER_LOCK = $(BASE_DIR)/composer.lock
# PHP CS FIXER STUFF
PHPCS_FIXER_SCRIPT = $(VENDOR_DIR)/bin/php-cs-fixer
PHPCS_FIXER_LOCK = $(BASE_DIR)/.php_cs.cache
# PHPUNIT STUFF
PHPUNIT_SCRIPT = $(VENDOR_DIR)/bin/phpunit
PHPUNIT_BOOTSTRAP_SCRIPT = $(UNITTESTS_DIR)/Bootstrap.php
PHPUNIT_LOCK = $(BASE_DIR)/.phpunit.lock
# PHPSTAN STUFF
PHPSTAN_SCRIPT = $(VENDOR_DIR)/bin/phpstan
PHPSTAN_LOCK = $(BASE_DIR)/.phpstan.lock

# TARGET ALIASES
INSTALL_TARGET = "$(INSTALL_PREFIX)usr/share/balloon"
COMPOSER_TARGET = $(COMPOSER_LOCK)
PHPCS_CHECK_TARGET = $(PHPCS_FIXER_LOCK)
PHPUNIT_TARGET = $(PHPUNIT_LOCK)
PHPSTAN_TARGET = $(PHPSTAN_LOCK)
BUILD_TARGET = $(COMPOSER_TARGET) $(PHPCS_CHECK_TARGET) $(PHPUNIT_TARGET) $(PHPSTAN_TARGET)

# MACROS
macro_find_phpfiles = $(shell find $(1) -type f -name "*.php")

# SOURCECODE FILESETS
PHP_FILES = $(call macro_find_phpfiles,$(SRC_DIR))
PHP_CORE_FILES = $(call macro_find_phpfiles,$(CORE_DIR))
PHP_CORE_API_FILES = $(call macro_find_phpfiles,$(CORE_API_DIR))
PHP_TEST_FILES = $(call macro_find_phpfiles,$(TESTS_DIR))
PHP_UNITTEST_FILES = $(call macro_find_phpfiles,$(UNITTESTS_DIR))

# TARGETS
.PHONY: all
all: build


.PHONY: clean
clean: mostlyclean
	@-test ! -d $(VENDOR_DIR) || rm -rfv $(VENDOR_DIR)/*


.PHONY: mostlyclean
mostlyclean:
	@-test ! -f $(TAR) || rm -fv $(TAR)
	@-test ! -d $(BUILD_DIR) || rm -rfv $(BUILD_DIR)
	@-test ! -f $(DIST_DIR)/*.deb || rm -fv $(DIST_DIR)/*.deb
	@-test ! -f $(COMPOSER_LOCK) || rm -fv $(COMPOSER_LOCK)
	@-test ! -f $(PHPCS_FIXER_LOCK) || rm -fv $(PHPCS_FIXER_LOCK)
	@-test ! -f $(PHPUNIT_LOCK) || rm -fv $(PHPUNIT_LOCK)
	@-test ! -f $(PHPSTAN_LOCK) || rm -fv $(PHPSTAN_LOCK)


.PHONY: deps
deps: composer


.PHONY: build
build: $(BUILD_TARGET)


.PHONY: dist
dist: tar deb

.PHONY: tar
tar: $(TAR)

$(TAR): $(BUILD_TARGET)
	$(COMPOSER_BIN) update --no-dev
	@-test ! -f $(TAR) || rm -fv $(TAR)
	@-test -d $(DIST_DIR) || mkdir $(DIST_DIR)
	@-test ! -d $(BUILD_DIR) || rm -rfv $(BUILD_DIR)
	@mkdir $(BUILD_DIR)
	@cp -Rp $(CONFIG_DIR) $(BUILD_DIR)
	@cp -Rp $(VENDOR_DIR) $(BUILD_DIR)
	@cp -Rp $(SRC_DIR) $(BUILD_DIR)
	@mkdir $(BUILD_DIR)/log

	@tar -czf $(TAR) -C $(BUILD_DIR) .
	@rm -rf $(BUILD_DIR)

	$(COMPOSER_BIN) update
	@touch $@

.PHONY: composer
composer: $(COMPOSER_TARGET)

$(COMPOSER_TARGET) $(PHPCS_FIXER_SCRIPT) $(PHPUNIT_SCRIPT) $(PHPSTAN_SCRIPT): $(BASE_DIR)/composer.json
	$(COMPOSER_BIN) update
	@touch $@

.PHONY: phpcs
phpcs: $(PHPCS_CHECK_TARGET)

$(PHPCS_CHECK_TARGET): $(PHPCS_FIXER_SCRIPT) $(PHP_FILES) $(COMPOSER_LOCK)
	$(PHP_BIN) $(PHPCS_FIXER_SCRIPT)  fix --config=.php_cs.dist -v --dry-run --allow-risky=yes --stop-on-violation --using-cache=no
	@touch $@

.PHONY: test
test: $(PHPUNIT_TARGET)

.PHONY: phpunit
phpunit: $(PHPUNIT_TARGET)

$(PHPUNIT_TARGET): $(PHPUNIT_SCRIPT) $(PHP_FILES) $(PHP_UNITTEST_FILES)
	$(PHP_BIN) $(PHPUNIT_SCRIPT) --stderr --debug -c phpunit.xml
	@touch $@

.PHONY: phpstan
phpstan: $(PHPSTAN_TARGET)

$(PHPSTAN_TARGET): $(PHPSTAN_SCRIPT) $(PHP_FILES) $(PHP_TEST_FILES)
	$(PHP_BIN) $(PHPSTAN_SCRIPT) analyse -c phpstan.neon $(SRC_DIR) $(TESTS_DIR)
	@touch $@

.PHONY: install
install: $(INSTALL_TARGET)

$(INSTALL_TARGET): $(BUILD_TARGET)
	$(COMPOSER_BIN) update --no-dev
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/scripts
	@mkdir -p $(BUILD_DIR)/usr/share/balloon/bin/console
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@rsync -a --exclude='.git' $(VENDOR_DIR) $(BUILD_DIR)/usr/share/balloon
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.systemd $(BUILD_DIR)/usr/share/balloon/scripts
	@cp  $(BASE_DIR)/packaging/balloon-jobs.service.upstart $(BUILD_DIR)/usr/share/balloon/scripts
	@cp -Rp $(SRC_DIR)/cgi-bin/cli.php $(BUILD_DIR)/usr/share/balloon/bin/console/ballooncli
	@cp -Rp $(SRC_DIR)/httpdocs $(BUILD_DIR)/usr/share/balloon/bin
	@cp -Rp $(SRC_DIR)/{lib,app} $(BUILD_DIR)/usr/share/balloon/src
	@cp -Rp $(SRC_DIR)/.container.config.php $(BUILD_DIR)/usr/share/balloon/src
	@mkdir -p $(BUILD_DIR)/etc/balloon
	@cp $(CONFIG_DIR)/config.yaml.dist $(BUILD_DIR)/etc/balloon
	@cp -Rp $(BUILD_DIR)/* $(INSTALL_PREFIX)
	@cp -Rp $(BASE_DIR)/packaging/debian/nginx.conf /etc/nginx/conf.d/balloon.conf
	@cp -Rp $(BASE_DIR)/packaging/debian/nginx-server.conf /etc/nginx/conf.d/balloon/server.conf
	$(COMPOSER_BIN) update
