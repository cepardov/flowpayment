<head>
    {block name='head'}
        {include file='_partials/head.tpl'}
    {/block}
</head>
<body>
{hook h='displayAfterBodyOpeningTag'}
<main>
    <!-- Menu part-->
    <header id="header">
        {block name='header'}
            {include file='_partials/header.tpl'}
        {/block}
    </header>
    <!-- Header part ends -->
    <section id="wrapper">
        <div class="container">
            <section id="main">
                <section id="content" class="page-content card card-block">
                    <div class="table-responsive-row clearfix">
                        <p>
                            {$error_flow}
                        </p>
                    </div>
                </section>
            </section>
        </div>
    </section>
    <!-- Footer starts -->
    <footer id="footer">
        {block name="footer"}
            {include file="_partials/footer.tpl"}
        {/block}
    </footer>
    <!-- Footer Ends -->
    {block name='javascript_bottom'}
        {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
    {/block}
    {hook h='displayBeforeBodyClosingTag'}
</main>
</body>
