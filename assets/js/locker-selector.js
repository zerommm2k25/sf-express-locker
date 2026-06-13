(function($) {
    'use strict';

    var sfLocker = {
        init: function() {
            this.cacheSelectors();
            this.bindEvents();
        },

        cacheSelectors: function() {
            this.$body = $('body');
            this.$tr = $('.sf-locker-selector');
            this.$container = $('.sf-locker-selector-inner');
            this.$regionSelect = $('#sf-locker-region');
            this.$districtSelect = $('#sf-locker-district');
            this.$searchInput = $('#sf-locker-search-input');
            this.$resultsList = $('#sf-locker-results');
            this.$selectedDisplay = $('#sf-locker-selected');
            this.$hiddenInput = $('#sf_locker_code');
            this.$selectedCode = $('#sf-locker-selected-code');
            this.$selectedAddress = $('#sf-locker-selected-address');
            this.$shippingFields = $('.woocommerce-shipping-fields');
            this.$loader = $('.sf-locker-loader');
            this.$nonceField = $('#sf_locker_nonce');
            this.searchTimer = null;
        },

        bindEvents: function() {
            var self = this;

            this.$regionSelect.on('change', function() {
                self.onRegionChange();
            });

            this.$districtSelect.on('change', function() {
                self.searchLockers();
            });

            this.$searchInput.on('input', function() {
                clearTimeout(self.searchTimer);
                self.searchTimer = setTimeout(function() {
                    self.searchLockers();
                }, 300);
            });

            this.$resultsList.on('click', '.sf-locker-item', function() {
                self.selectLocker($(this));
            });

            this.$body.on('updated_checkout', function() {
                self.onCheckoutUpdate();
            });

            if (this.$hiddenInput.val()) {
                this.restoreSelection();
            }
        },

        onCheckoutUpdate: function() {
            this.cacheSelectors();
            this.bindEvents();

            if (this.$tr.length) {
                this.hideShippingFields();

                var savedCode = this.$hiddenInput.val();
                if (savedCode) {
                    this.restoreSelection();
                }
            } else {
                this.showShippingFields();
            }
        },

        onRegionChange: function() {
            var region = this.$regionSelect.val();
            var self = this;

            this.$districtSelect.prop('disabled', true).html('<option value="">選擇地區</option>');
            this.$resultsList.hide();

            if (!region) {
                return;
            }

            this.$loader.show();

            $.ajax({
                url: sfLockerData.ajax_url.replace('%%endpoint%%', 'sf_get_districts_by_region'),
                type: 'POST',
                data: {
                    region: region,
                    security: this.getNonce()
                },
                success: function(response) {
                    self.$loader.hide();
                    if (response.success) {
                        var html = '<option value="">選擇地區</option>';
                        $.each(response.data, function(i, d) {
                            html += '<option value="' + d + '">' + d + '</option>';
                        });
                        self.$districtSelect.html(html).prop('disabled', false);
                    }
                },
                error: function() {
                    self.$loader.hide();
                }
            });
        },

        getNonce: function() {
            if (this.$nonceField.length) {
                return this.$nonceField.val();
            }
            return '';
        },

        searchLockers: function() {
            var self = this;
            var keyword = this.$searchInput.val();
            var district = this.$districtSelect.val();
            var region = this.$regionSelect.val();

            this.$resultsList.hide();

            if (!keyword && !district) {
                return;
            }

            this.$loader.show();

            $.ajax({
                url: sfLockerData.ajax_url.replace('%%endpoint%%', 'sf_search_lockers'),
                type: 'POST',
                data: {
                    keyword: keyword,
                    district: district,
                    region: region,
                    security: this.getNonce()
                },
                success: function(response) {
                    self.$loader.hide();
                    if (response.success) {
                        self.$resultsList.html(response.data.html).show();
                    }
                },
                error: function() {
                    self.$loader.hide();
                    self.$resultsList.html('<li class="sf-locker-no-results">搜尋失敗，請重試</li>').show();
                }
            });
        },

        restoreSelection: function() {
            var code = this.$hiddenInput.val();
            var self = this;

            $.ajax({
                url: sfLockerData.ajax_url.replace('%%endpoint%%', 'sf_get_locker'),
                type: 'POST',
                data: {
                    code: code,
                    security: this.getNonce()
                },
                success: function(response) {
                    if (response.success) {
                        var locker = response.data;
                        self.$selectedCode.text(locker.code);
                        self.$selectedAddress.text(locker.district + ' ' + locker.address_zh);
                        self.$selectedDisplay.show();
                        self.hideShippingFields();
                        self.autoFillShippingAddress(locker);
                        self.updateShippingMethodInfo(locker);
                    }
                }
            });
        },

        selectLocker: function($item) {
            this.$resultsList.find('.sf-locker-item').removeClass('selected');
            $item.addClass('selected');

            var code = $item.data('code');
            var address = $item.data('address');
            var district = $item.data('district');

            this.$hiddenInput.val(code);
            this.$selectedCode.text(code);
            this.$selectedAddress.text(district + ' ' + address);
            this.$selectedDisplay.show();

            this.$searchInput.val('');
            this.$regionSelect.val('');
            this.$districtSelect.prop('disabled', true).html('<option value="">選擇地區</option>');
            this.$resultsList.hide();

            this.hideShippingFields();
            this.autoFillShippingAddress({ code: code, address_zh: address, district: district });
            this.updateShippingMethodInfo({ code: code, address_zh: address, district: district });
        },

        updateShippingMethodInfo: function(locker) {
        },

        autoFillShippingAddress: function(locker) {
            var addressInput = $('#shipping_address_1');
            var cityInput = $('#shipping_city');

            if (addressInput.length) {
                addressInput.val(locker.address_zh).trigger('change');
            }

            if (cityInput.length && locker.district) {
                cityInput.val(locker.district).trigger('change');
            }
        },

        hideShippingFields: function() {
            this.$shippingFields.addClass('sf-locker-active');
        },

        showShippingFields: function() {
            this.$shippingFields.removeClass('sf-locker-active');
        }
    };

    $(document).ready(function() {
        if ($('.sf-locker-selector').length) {
            sfLocker.init();
        }
    });

})(jQuery);
