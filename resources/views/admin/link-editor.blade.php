<dialog class="media-picker rich-link-dialog" data-rich-link aria-labelledby="rich-link-title">
    <form data-rich-link-form>
        <header class="media-dialog-header">
            <h2 id="rich-link-title">ویرایش پیوند</h2>
            <button type="button" class="media-icon-button media-close" data-link-close aria-label="بستن">×</button>
        </header>
        <div class="media-picker-body">
            <label>متن پیوند<input name="label" required maxlength="500"></label>
            <label>نشانی پیوند<input name="url" dir="ltr" required maxlength="500" placeholder="https://…"></label>
            <p data-link-error role="alert"></p>
        </div>
        <footer class="media-dialog-footer">
            <button type="button" class="admin-button" data-link-remove hidden>حذف پیوند</button>
            <button type="submit" class="admin-button admin-primary">ثبت پیوند</button>
        </footer>
    </form>
</dialog>
