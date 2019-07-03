<div>
<table class="admintable" width="100%">
    <tr>
        <td class="key">
            Почтовый индекс магазина
        </td>
        <td>
            <input type="text" id="jform_params_zip_code" name="params[zip_code]" value="<?php echo $config['zip_code']; ?>">
        </td>
    </tr>
    <tr>
        <td class="key">
            Логин (Account) интеграционного аккаунта
        </td>
        <td>
            <input type="text" id="jform_params_auth_login" name="params[authLogin]" value="<?php echo $config['authLogin']; ?>">
        </td>
    </tr>
    <tr>
        <td class="key">
            Пароль (Secure_password) интеграционного аккаунта
        </td>
        <td>
            <input type="text" id="jform_params_auth_password" name="params[authPassword]" value="<?php echo $config['authPassword']; ?>">
        </td>
    </tr>
    <tr>
        <td class="key">
            Отладка
        </td>
        <td>
            <input type="radio" id="jform_params_debug0" name="params[debug]" value="0"<?php echo $checkedNo; ?>>
            <label for="jform_params_debug0">Нет</label>
            <input type="radio" id="jform_params_debug1" name="params[debug]" value="1"<?php echo $checkedYes; ?>>
            <label for="jform_params_debug1">Да</label>
        </td>
    </tr>
</table>


<table class="admintable" width="100%">
    <tbody>
    <?php foreach ($shippingMetods as $k => $v) : ?>
    <tr>
        <td class="key">
            <?php echo $v['name']; ?>
        </td>
        <td>

                <label>
                    <input type="radio" name="params[shippingMetods][<?php echo $k; ?>]"
                           value="0" <?php echo (empty($config['shippingMetods'][$k])) ? 'checked="checked"' : ''; ?>/>
                    Нет
                </label>
        </td>
            <?php foreach ($sm as $metod) : ?>
        <td>
            <label>
                <input type="radio" name="params[shippingMetods][<?php echo $k; ?>]"
                       value="<?php echo $metod->id; ?>" <?php echo ($config['shippingMetods'][$k] == $metod->id) ? 'checked="checked"' : ''; ?>/>
                <?php echo $metod->name; ?>
            </label>
        </td>
    <?php endforeach;?>
    </tr>
    <?php endforeach; ?>
</table>
</div>
<div class="clr"></div>