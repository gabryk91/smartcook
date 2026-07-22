app_name=smartcook
version=1.0.1
build_dir=build
release_name=$(app_name)-$(version)-nextcloud.zip

.PHONY: build build-js build-compat clean package lint test

build: build-js build-compat

build-js:
	npm install
	npm run build

build-compat:
	npm run build:compat

lint:
	composer lint
	npm run lint
	npm run stylelint
	npm run typecheck
	npm run check:compat

test:
	composer test:smoke
	composer test:unit

clean:
	rm -rf $(build_dir) node_modules .phpunit.cache

package: build
	rm -rf $(build_dir)
	mkdir -p $(build_dir)/$(app_name)
	rsync -a --exclude-from=.nextcloudignore ./ $(build_dir)/$(app_name)/
	cd $(build_dir) && zip -qr $(release_name) $(app_name)
	@echo "Created $(build_dir)/$(release_name)"
