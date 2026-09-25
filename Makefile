SHELL := /bin/sh

.PHONY: help clean release release-note e2e-dashboard-hybrid docker-up docker-rebuild

help:
	@echo "Available targets:"
	@echo "  make clean         Remove local runtime/build/cache artifacts"
	@echo "  make release       Auto-suggest next vYYYY.MM.patch + plain-text/Markdown changelog note"
	@echo "  make release-note  Alias for make release"
	@echo "  make e2e-dashboard-hybrid  Run Playwright hybrid dashboard regression against localhost (set PLAYWRIGHT_PYTHON if needed)"
	@echo "  make docker-up     Start docker compose (notes when APP_KEY will be generated)"
	@echo "  make docker-rebuild  Rebuild image and start docker compose"

clean:
	@echo "Cleaning local runtime/build/cache artifacts..."
	@rm -f .phpunit.result.cache
	@rm -rf public/build public/hot
	@if [ -d bootstrap/cache ]; then find bootstrap/cache -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -d storage/logs ]; then find storage/logs -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -d storage/framework/cache ]; then find storage/framework/cache -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -d storage/framework/sessions ]; then find storage/framework/sessions -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -d storage/framework/views ]; then find storage/framework/views -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -d storage/framework/testing ]; then find storage/framework/testing -mindepth 1 ! -name '.gitignore' -exec rm -rf {} +; fi
	@if [ -f artisan ] && [ -d vendor ]; then php artisan optimize:clear >/dev/null || true; fi
	@echo "Clean complete."

release:
	@sh scripts/release.sh

release-note: release

e2e-dashboard-hybrid:
	@sh scripts/run-dashboard-hybrid-playwright.sh --url http://localhost:8000/

docker-up:
	@if grep -q 'APP_KEY: "base64:REPLACE_WITH_YOUR_GENERATED_KEY"' docker-compose.yml; then \
		echo "No APP_KEY set in docker-compose.yml. The container generates one on first start"; \
		echo "and saves it in the storage volume (storage/app/.app-key)."; \
	fi
	@echo "Starting docker compose stack..."
	@docker compose up -d
	@echo "Docker stack is up. Open http://localhost:8080 (or your configured port)."

docker-rebuild:
	@if grep -q 'APP_KEY: "base64:REPLACE_WITH_YOUR_GENERATED_KEY"' docker-compose.yml; then \
		echo "No APP_KEY set in docker-compose.yml. The container generates one on first start"; \
		echo "and saves it in the storage volume (storage/app/.app-key)."; \
	fi
	@echo "Rebuilding image and starting docker compose stack..."
	@docker compose build --no-cache
	@docker compose up -d
	@echo "Docker stack rebuilt and running."
