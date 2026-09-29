@php($legal = $ctx->legal)
<section class="section">
    <div class="container narrow prose">
        <h2>Responsable du traitement</h2>
        <p>{{ $legal['company_name'] }}@if ($legal['address']), {{ $legal['address'] }}@endif. Contact : @if ($site['email'])<a href="mailto:{{ $site['email'] }}">{{ $site['email'] }}</a>@else{{ $site['phone'] }}@endif.</p>

        <h2>Formulaire de contact</h2>
        <p>Les informations saisies dans le formulaire (nom, email, téléphone, message) servent uniquement à répondre à votre demande. Elles ne sont ni revendues ni utilisées à des fins publicitaires. Elles sont conservées {{ $legal['retention_months'] }} mois au maximum, puis supprimées.</p>
        <p>Base légale : votre consentement, exprimé en cochant la case prévue lors de l'envoi.</p>

        <h2>Mesure d'audience</h2>
        <p>Ce site mesure sa fréquentation de façon anonyme, sans cookie et sans conserver votre adresse IP. Aucune donnée ne permet de vous identifier.</p>

        <h2>Cookies</h2>
        <p>Ce site ne dépose aucun cookie. Si vous choisissez d'afficher la carte Google Maps, Google peut déposer ses propres cookies, soumis à sa <a href="https://policies.google.com/privacy" rel="noopener">politique de confidentialité</a>.</p>

        <h2>Vos droits</h2>
        <p>Vous pouvez demander l'accès, la rectification ou la suppression de vos données en écrivant à l'adresse ci-dessus. Vous pouvez également adresser une réclamation à la <a href="https://www.cnil.fr" rel="noopener">CNIL</a>.</p>
    </div>
</section>
