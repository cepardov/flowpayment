
{extends file='page.tpl'}

{block name='page_content'}
    <div class="row">
        <div class="col-md-12">
            <h3 class="h3 card-title">{l s='Error'}</h3>
        </div>
        <div class="col-md-12">
            <p>{l s='Ha ocurrido un error inesperado. Por favor, intentelo de nuevo.'}</p>
            {if $errorMessage }
                <p>{$errorMessage}</p>                
            {/if}
        </div>
    </div>
{/block}