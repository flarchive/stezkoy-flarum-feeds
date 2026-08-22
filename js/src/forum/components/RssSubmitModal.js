import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Icon from 'flarum/common/components/Icon';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import app from 'flarum/forum/app';

export default class RssSubmitModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.url = '';
    this.siteTitle = '';
    this.feedId = null;
    this.feedStatus = null;
    this.loading = true;

    this.loadExistingFeed();
  }

  loadExistingFeed() {
    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/user-rss-feeds',
      })
      .then((response) => {
        if (response.data.length > 0) {
          const userFeed = response.data[0];
          this.feedId = userFeed.id;
          this.url = userFeed.attributes.url;
          this.siteTitle = userFeed.attributes.title;
          this.feedStatus = userFeed.attributes.status || 'pending';
        }

        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.submit_modal.load_error'));
        m.redraw();
      });
  }

  className() {
    return 'RssSubmitModal Modal--small';
  }

  title() {
    return app.translator.trans('stezkoy-rss.forum.submit_modal.title');
  }

  content() {
    if (this.loading) {
      return (
        <div className="Modal-body">
          <LoadingIndicator display="block" />
        </div>
      );
    }

    return (
      <div className="Modal-body">
        {this.feedId && this.feedStatus && (
          <div className={`RssSubmitModal-status RssSubmitModal-status--${this.feedStatus}`}>
            <Icon name={this.feedStatus === 'approved' ? 'fas fa-check' : 'fas fa-clock'} />
            {app.translator.trans(
              this.feedStatus === 'approved'
                ? 'stezkoy-rss.forum.submit_modal.status_approved'
                : 'stezkoy-rss.forum.submit_modal.status_pending'
            )}
          </div>
        )}
        <div className="Form-group">
          <label>{app.translator.trans('stezkoy-rss.forum.submit_modal.site_title_label')}</label>
          <input
            className="FormControl"
            placeholder={app.translator.trans('stezkoy-rss.forum.submit_modal.site_title_placeholder')}
            value={this.siteTitle}
            oninput={(e) => {
              this.siteTitle = e.target.value;
            }}
          />
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('stezkoy-rss.forum.submit_modal.url_label')}</label>
          <input
            className="FormControl"
            placeholder={app.translator.trans('stezkoy-rss.forum.submit_modal.url_placeholder')}
            value={this.url}
            oninput={(e) => {
              this.url = e.target.value;
            }}
          />
        </div>
        <div className="Form-group">
          <Button className="Button Button--primary" onclick={this.onsubmit.bind(this)} loading={this.loading}>
            {app.translator.trans('stezkoy-rss.forum.submit_modal.submit_button')}
          </Button>
        </div>
      </div>
    );
  }

  onsubmit(e) {
    e.preventDefault();
    this.loading = true;

    if (!this.url || !this.siteTitle) {
      app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.submit_modal.validation_error'));
      this.loading = false;
      return;
    }

    const method = this.feedId ? 'PATCH' : 'POST';
    const apiUrl = this.feedId ? app.forum.attribute('apiUrl') + `/rss-feeds/${this.feedId}` : app.forum.attribute('apiUrl') + '/rss-feeds';

    app
      .request({
        method: method,
        url: apiUrl,
        body: {
          data: {
            type: 'rss-feeds',
            id: this.feedId ? String(this.feedId) : undefined,
            attributes: {
              url: this.url,
              title: this.siteTitle,
            },
          },
        },
      })
      .then((response) => {
        this.loading = false;
        this.feedId = response?.data?.id || this.feedId;
        app.alerts.show({ type: 'success' }, app.translator.trans('stezkoy-rss.forum.submit_modal.success_message'));
        this.hide();
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.submit_modal.submit_error'));
        m.redraw();
      });
  }
}
