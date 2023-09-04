{extends file='page.tpl'}

{block name='page_content'}
    <div class="row">
        <div class="col-md-12">
            <h3 class="h3 card-title">{l s='Error en el pago'}</h3>
        </div>
        <div class="col-md-12">
            <div class="alert alert-danger" role="alert">
                {l s='Ha ocurrido un error procesando su pago. Por favor, intentelo de nuevo.'}
                </div>
            </div>
        </div>
    </div>
{/block}