{*
* 2018 Tuxpan
* Template para configurar plugin Flow
*
*  @author flow.cl
*  @copyright  2018 Tuxpan
*  @version: 2.0
*  @Email: soporte@tuxpan.com
*  @Date: 15-05-2018 11:00
*  @Last Modified by: Tuxpan
*  @Last Modified time: 26-02-2018
*}
<img src="{$img_header}"/>

{if $noErrors and $isSubmitted}
	<div class="bootstrap">
		<div class="alert alert-success" role="alert">{l s="La configuración fue guardada correctamente." mod="flowpaymentflow"}</div>
	</div>
{/if}

{if !empty($errors) and $isSubmitted}
    <div class="bootstrap">
        <div class="alert alert-danger" role="alert">{l s="Se detectaron errores en el formulario." mod="flowpaymentflow"}</div>
    </div>
{/if}

<h2>{l s='Pago usando Flow' mod='flowpaymentflow'}</h2>

<fieldset>
    <legend><img src="../img/admin/warning.gif"/>{l s='Information' mod='flowpaymentflow'}</legend>
    <div class="margin-form">Module version: {$version}</div>

</fieldset>

<form action="{$post_url}" method="post" enctype="multipart/form-data" style="clear: both; margin-top: 10px;">
    <fieldset>

        <legend><img src="../img/admin/contact.gif"/>{l s='Settings' mod='flowpaymentflow'}</legend>

        <label for="platformType">{l s='Plataforma de Flow' mod='flowpaymentflow'}</label>
        <div class="margin-form">
            <select name="platformType">
                <option value="test" {if $data_platformType eq "test"}selected{/if}>Plataforma sandbox de Flow</option>
                <option value="real" {if $data_platformType eq "real"}selected{/if}>Plataforma de producción de Flow</option>
            </select>
        </div>
        {if isset($errors.title)}
            <div class="error">
                <p>{$errors.title}</p>
            </div>
        {/if}

        <label for="title">{l s='Nombre del medio de pago' mod='flowpaymentflow'}</label>

        <div class="margin-form"><input type="text" size="60" id="title" name="title" maxsize="100"
                                        value="{$data_title}" placeholder="Ingrese el nombre que se mostrará al usuario"/></div>
        {if isset($errors.apiKey)}
            <div class="error">
                <p>{$errors.apiKey}</p>
            </div>
        {/if}
        <label for="apiKey">{l s='Llave integracion seguridad (Api Key)' mod='flowpaymentflow'}</label>
        <div class="margin-form"><input type="text" size="40" name="apiKey" value="{$data_apiKey}"/></div>
        
        {if isset($errors.additional)}
            <div class="error">
                <p>{$errors.additional}</p>
            </div>
        {/if}

        <label for="additional">{l s='Cobro adicional (en %)' mod='flowpaymentflow'}</label>

        <div class="margin-form"><input type="text" size="5" id="additional" name="additional"
                                        value="{$data_additional}"/></div>
                                        
        {if isset($errors.logoSmall)}
            <div class="error">
                <p>{$errors.logoSmall}</p>
            </div>
        {/if}
		<label for="logoSmall">{l s='Logo que se mostrará' mod='flowpaymentflow'}</label>

        <div class="margin-form"><img src="{$data_logoSmall}"/><input type="file" name="logoSmall" /></div>
        
        {if isset($errors.returnUrl)}
            <div class="error">
                <p>{$errors.returnUrl}</p>
            </div>
        {/if}

        {if isset($return_url)}

            <label for="additional">{l s='URL de retorno' mod='flowpaymentflow'}</label>
            <div class="margin-form">
                <input type="text" size="60" id="returnUrl" name="returnUrl" value="{$return_url}"/>
            </div>
        {/if}

        <center>
            <input type="submit" name="flow_updateSettings" value="{l s='Save Settings' mod='flowpaymentflow'}"
                       class="button" style="cursor: pointer; display:"/>
        </center>
    </fieldset>
</form>
