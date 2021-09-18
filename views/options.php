<?php
/**
 * @var string $base_url
 * @var string $error
 */

$sheets_api_acc = 'sacc-1@privet-yotube-azzrael-code.iam.gserviceaccount.com';
?>

<?php if(isset($message)): ?>
<div class="alert alert-<?= isset($error) ? "danger" : "success" ?>"><?= $message ?></div>
<?php endif; ?>


<div class="border rounded py-2 px-4 bg-light mt-2 mb-4">
    <form id="azz-tinvest-form" action="https://www.youtube.com/c/AzzraelCode" method="get" class="m-2">

        <input type="hidden" name="nonce" value="<?= wp_create_nonce( "azztinvest_action" ) ?>">

        <div class="form-group mb-2">
            <label for="tinvest_token_real">Токен для РЕАЛЬНОГО счета Тинькофф Инвестиции</label>
            <input type="password" class="form-control" name="tinvest_token_real" value="<?= @$_POST['tinvest_token_real'] ?>" autofocus>
            <small class="form-text text-muted">НЕ песочница, токен нигде не сохраняется</small>
        </div>

        <div class="form-group mb-2">
            <label for="google_spreadsheet_id">ID таблицы Google</label>
            <input type="text" class="form-control" name="google_spreadsheet_id" value="<?= @$_POST['google_spreadsheet_id'] ?>">
            <small class="form-text text-muted">сначала дай доступ уровня Редактор для <?= $sheets_api_acc ?></small>
        </div>

        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" name="create_sheet" value="1" checked>
            <label for="create_sheet" class="form-check-label">
                Создать новый лист <br>
                <small class="form-text text-muted">
                    Если снять, буду пытаться писать в Лист1. Содержимое удалю. Если нет Лист1 - выкину ошибку.
                </small>
            </label>

        </div>

        <div class="form-group" id="azz-tinvest-submit" style="display: none;">
            <input type="submit" class="btn btn-primary" value="Создать отчет">
        </div>
    </form>

</div>


<!-- Простенькая защита от ботов -->
<script type="text/javascript">
    let form_url = '<?= $base_url ?>';
    setTimeout(function () {
        let form = document.getElementById("azz-tinvest-form");
        form.setAttribute('action', form_url);
        form.setAttribute('method', 'post');
        let btns = document.getElementById("azz-tinvest-submit");
        btns.removeAttribute('style');
    }, 2000);

</script>