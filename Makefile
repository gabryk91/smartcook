app_name=smartcook
version=1.0.1
build_dir=build
release_name=$(app_name)-$(version)-nextcloud.zip

.PHONY: clean package test

lint:
	composer lint

test:
	composer test:smoke
	composer test:unit

clean:
	rm -rf $(build_dir) node_modules .phpunit.cache

package:
	rm -rf $(build_dir)
	mkdir -p $(build_dir)/$(app_name)
	rsync -a --exclude-from=.nextcloudignore ./ $(build_dir)/$(app_name)/
	cd $(build_dir) && zip -qr $(release_name) $(app_name)
	@echo "Created $(build_dir)/$(release_name)"
