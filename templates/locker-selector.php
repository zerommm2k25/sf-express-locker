<?php
defined( 'ABSPATH' ) || exit;

$selected_code   = isset( $selected_code ) ? $selected_code : '';
$selected_locker = isset( $selected_locker ) ? $selected_locker : null;
$regions         = isset( $regions ) ? $regions : array();

$region_labels = array(
    'HK'  => '香港島',
    'KLN' => '九龍',
    'NT'  => '新界',
);
?>
<tr class="sf-locker-selector">
    <td colspan="2">
        <div class="sf-locker-selector-inner">
            <h3><span class="required">*</span>選擇順豐智能櫃</h3>

            <div class="sf-locker-search-row">
                <select id="sf-locker-region">
                    <option value="">選擇區域</option>
                    <?php foreach ( $regions as $r ) : ?>
                        <option value="<?php echo esc_attr( $r ); ?>"><?php echo esc_html( isset( $region_labels[ $r ] ) ? $region_labels[ $r ] : $r ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="sf-locker-district" disabled>
                    <option value="">選擇地區</option>
                </select>
            </div>

            <div class="sf-locker-search-box">
                <input type="text" id="sf-locker-search-input" class="sf-locker-search-input"
                       placeholder="輸入智能櫃編號或地址搜尋..." autocomplete="off">
            </div>

            <div class="sf-locker-loader">載入中...</div>

            <ul id="sf-locker-results"></ul>

            <div class="sf-locker-selected" id="sf-locker-selected"
                 <?php echo $selected_locker ? '' : 'style="display:none;"'; ?>>
                <div class="sf-locker-selected-main">
                    <span id="sf-locker-selected-code" class="locker-code"><?php echo esc_html( $selected_code ); ?></span>
                    <span id="sf-locker-selected-address" class="locker-address">
                        <?php echo $selected_locker ? esc_html( $selected_locker['district'] . ' ' . $selected_locker['address_zh'] ) : ''; ?>
                    </span>
                </div>
                <span class="sf-locker-change" onclick="jQuery('#sf-locker-selected').hide();jQuery('#sf-locker-search-input').val('');jQuery('#sf-locker-region').val('').trigger('change');jQuery('#sf-locker-results').empty().hide();jQuery('#sf-locker-region').focus();">
                    更換智能櫃
                </span>
            </div>

            <input type="hidden" name="sf_locker_code" id="sf_locker_code"
                   value="<?php echo esc_attr( $selected_code ); ?>">

            <?php wp_nonce_field( 'sf-locker-search', 'sf_locker_nonce' ); ?>
        </div>
    </td>
</tr>
