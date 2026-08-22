import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import app from 'flarum/admin/app';

export default class RssFeedPreviewModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = true;
    this.preview = null;
    this.error = null;

    this.load();
  }

  className() {
    return 'RssFeedPreviewModal Modal--large';
  }

  title() {
    const title = this.attrs.feed?.title() || app.translator.trans('stezkoy-rss.admin.page.preview_default_title');

    return app.translator.trans('stezkoy-rss.admin.page.preview_title', { title });
  }

  content() {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <LoadingIndicator display="block" />
        </div>
      );
    }

    if (this.error) {
      return (
        <div className="Modal-body">
          <p className="helpText RssFeedPreviewModal-error">{app.translator.trans('stezkoy-rss.admin.page.preview_error')}</p>
          <pre className="RssFeedPreviewModal-errorDetail">{this.error}</pre>
        </div>
      );
    }

    const items = this.preview?.items || [];

    return (
      <div className="Modal-body">
        {!items.length ? (
          <p className="helpText">{app.translator.trans('stezkoy-rss.admin.page.preview_empty')}</p>
        ) : (
          <div className="RssFeedPreviewModal-list">
            {items.map((item, index) => (
              <div className="RssFeedPreviewModal-item" key={`${item.link || item.title}-${index}`}>
                <div className="RssFeedPreviewModal-itemMain">
                  <h4>{item.title || item.link}</h4>
                  {item.published_at && <time>{item.published_at}</time>}
                  {item.excerpt && <p>{item.excerpt}</p>}
                </div>
                {item.link && (
                  <Button className="Button" icon="fas fa-external-link-alt" onclick={() => window.open(item.link, '_blank', 'noopener,noreferrer')}>
                    {app.translator.trans('stezkoy-rss.admin.page.preview_open')}
                  </Button>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    );
  }

  load() {
    const feed = this.attrs.feed;

    app
      .request({
        method: 'GET',
        url: `${app.forum.attribute('apiUrl')}/rss-feeds/${feed.id()}/preview`,
      })
      .then((response) => {
        this.preview = response?.data?.attributes || {};
      })
      .catch((error) => {
        this.error = error?.response?.errors?.[0]?.detail || error?.message || String(error);
      })
      .finally(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
