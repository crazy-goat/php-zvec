ZVEC_VERSION ?= v0.2.0
MARCH        ?= nehalem
IMAGE        := zvec-builder

.PHONY: image ubuntu24 clean upload help

help:
	@echo "Usage:"
	@echo "  make image                  Build Docker toolchain image (cached)"
	@echo "  make ubuntu24               Build zvec for Ubuntu 24.04 x86_64"
	@echo "  make ubuntu24 MARCH=haswell  Build with specific -march"
	@echo "  make upload                 Upload tarball to GitHub release"
	@echo "  make clean                  Remove output files"
	@echo ""
	@echo "Available MARCH targets: nehalem sandybridge haswell broadwell skylake"
	@echo "Output goes to: out/ubuntu24-x86_64/"

image:
	docker build --platform linux/amd64 -f Dockerfile.zvec-ubuntu24 -t $(IMAGE) .

ubuntu24: image
	docker run --platform linux/amd64 --rm \
		-v zvec-src:/src/zvec \
		-v "$$(pwd)":/build \
		-e ZVEC_VERSION=$(ZVEC_VERSION) \
		-e MARCH=$(MARCH) \
		-e PLATFORM=ubuntu24-x86_64 \
		$(IMAGE) bash /build/build-zvec.sh

upload:
	@TARBALL="out/ubuntu24-x86_64/zvec-$(ZVEC_VERSION)-ubuntu24-x86_64.tar.gz"; \
	if [ ! -f "$$TARBALL" ]; then echo "ERROR: $$TARBALL not found. Run 'make ubuntu24' first."; exit 1; fi; \
	RELEASE="zvec-build-$(ZVEC_VERSION)"; \
	echo "==> Uploading $$TARBALL to release $$RELEASE..."; \
	gh release delete-asset "$$RELEASE" "$$(basename $$TARBALL)" --yes 2>/dev/null || true; \
	gh release upload "$$RELEASE" "$$TARBALL"

clean:
	rm -rf out/
