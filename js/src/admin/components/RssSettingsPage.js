import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import withAttr from 'flarum/common/utils/withAttr';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Form from 'flarum/common/components/Form';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import UserSelectionModal from 'flarum/common/components/UserSelectionModal';
import app from 'flarum/admin/app';
import RssFeedPreviewModal from './RssFeedPreviewModal';

export default class RssSettingsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.loadingFeeds = true;
    this.loadingTags = true;
    this.savingNewFeed = false;
    this.savingFeedUsers = {};
    this.resetNewFeed();

    app.store
      .find('rss-feeds')
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.admin.page.load_error'));
      })
      .finally(() => {
        this.loadingFeeds = false;
        m.redraw();
      });

    app.store
      .find('tags')
      .catch(() => {})
      .finally(() => {
        this.loadingTags = false;
        m.redraw();
      });
  }

  content() {
    const rssFeeds = app.store.all('rss-feeds');

    return (
      <div className="container RssFeed-container">
        <h2>{app.translator.trans('stezkoy-rss.admin.page.title')}</h2>

        <div className="RssFeedSettings">
          <Form>
            {this.buildSettingComponent({
              setting: 'stezkoy-rss.show_on_index',
              label: app.translator.trans('stezkoy-rss.admin.page.show_on_index_label'),
              help: app.translator.trans('stezkoy-rss.admin.page.show_on_index_help'),
              type: 'switch',
              default: '0',
            })}
            {this.buildSettingComponent({
              setting: 'stezkoy-rss.materialized_tag_id',
              label: app.translator.trans('stezkoy-rss.admin.page.materialized_tag_label'),
              help: app.translator.trans('stezkoy-rss.admin.page.materialized_tag_help'),
              type: 'select',
              options: this.tagOptions(),
              default: '',
              disabled: this.loadingTags,
            })}
            <div className="Form-group Form-controls">{this.submitButton()}</div>
          </Form>
        </div>

        {this.loadingFeeds ? <LoadingIndicator /> : (
          <table className="Table RssFeedTable">
            <thead>
              <tr>
                <th>{app.translator.trans('stezkoy-rss.admin.page.heading.title')}</th>
                <th>{app.translator.trans('stezkoy-rss.admin.page.heading.url')}</th>
                <th>{app.translator.trans('stezkoy-rss.admin.page.heading.user')}</th>
                <th>{app.translator.trans('stezkoy-rss.admin.page.heading.status')}</th>
                <th>{app.translator.trans('stezkoy-rss.admin.page.heading.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {rssFeeds.map((feed) => (
                <tr key={feed.id()}>
                  <td>
                    <input
                      className="FormControl"
                      type="text"
                      value={feed.title()}
                      oninput={withAttr('value', (value) => feed.pushAttributes({ title: value }))}
                      onblur={withAttr('value', (value) => this.updateFeed(feed, 'title', value))}
                    />
                  </td>
                  <td>
                    <input
                      className="FormControl"
                      type="text"
                      value={feed.url()}
                      oninput={withAttr('value', (value) => feed.pushAttributes({ url: value }))}
                      onblur={withAttr('value', (value) => this.updateFeed(feed, 'url', value))}
                    />
                  </td>
                  <td className="user">{this.userBindingControl(feed)}</td>
                  <td className="status">
                    <Switch state={feed.status() === 'approved'} onchange={() => this.toggleApproval(feed)} />
                  </td>
                  <td>
                    <div className="RssFeedActions">
                      <Button className="Button" icon="fas fa-eye" onclick={() => app.modal.show(RssFeedPreviewModal, { feed })}>
                        {app.translator.trans('stezkoy-rss.admin.page.view')}
                      </Button>
                      <Button className="Button Button--danger" icon="fas fa-trash" onclick={() => this.deleteFeed(feed)}>
                        {app.translator.trans('stezkoy-rss.admin.page.delete')}
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
              <tr className="RssFeedTable-newRow">
                <td>
                  <input
                    className="FormControl"
                    type="text"
                    value={this.newFeed.title() || ''}
                    placeholder={app.translator.trans('stezkoy-rss.admin.page.new_title')}
                    oninput={withAttr('value', (value) => this.newFeed.pushAttributes({ title: value }))}
                  />
                </td>
                <td>
                  <input
                    className="FormControl"
                    type="url"
                    value={this.newFeed.url() || ''}
                    placeholder={app.translator.trans('stezkoy-rss.admin.page.new_url')}
                    oninput={withAttr('value', (value) => this.newFeed.pushAttributes({ url: value }))}
                  />
                </td>
                <td className="user">{this.userBindingControl(this.newFeed, true)}</td>
                <td className="status">
                  <Switch state={this.newFeed.status() === 'approved'} onchange={() => this.toggleNewFeedStatus()} />
                </td>
                <td>
                  <Button
                    className="Button Button--primary"
                    icon="fas fa-plus"
                    loading={this.savingNewFeed}
                    disabled={!this.newFeedReady()}
                    onclick={() => this.createFeed()}
                  >
                    {app.translator.trans('stezkoy-rss.admin.page.add_new')}
                  </Button>
                </td>
              </tr>
            </tbody>
          </table>
        )}
      </div>
    );
  }

  tagOptions() {
    const options = {
      '': app.translator.trans('stezkoy-rss.admin.page.materialized_tag_none'),
    };

    app.store
      .all('tags')
      .slice()
      .sort((a, b) => {
        const aPos = a.position();
        const bPos = b.position();

        if (aPos === bPos) return a.name().localeCompare(b.name());
        if (aPos === null) return 1;
        if (bPos === null) return -1;

        return aPos - bPos;
      })
      .forEach((tag) => {
        const parent = tag.parent && tag.parent();
        options[tag.id()] = parent ? `${parent.name()} / ${tag.name()}` : tag.name();
      });

    return options;
  }

  resetNewFeed() {
    const user = app.session.user;

    this.newFeed = app.store.createRecord('rss-feeds', {
      attributes: {
        title: '',
        url: '',
        status: 'approved',
        user_id: user ? Number(user.id()) : null,
      },
      relationships: user ? {
        user: {
          data: {
            type: 'users',
            id: user.id(),
          },
        },
      } : {
        user: {
          data: null,
        },
      },
    });
  }

  newFeedReady() {
    return Boolean((this.newFeed.title() || '').trim() && (this.newFeed.url() || '').trim());
  }

  toggleApproval(feed) {
    const newStatus = feed.status() === 'approved' ? 'pending' : 'approved';
    feed.save({ status: newStatus });
  }

  toggleNewFeedStatus() {
    this.newFeed.pushAttributes({
      status: this.newFeed.status() === 'approved' ? 'pending' : 'approved',
    });
  }

  createFeed() {
    if (!this.newFeedReady() || this.savingNewFeed) return;

    this.savingNewFeed = true;

    this.newFeed
      .save({
        title: this.newFeed.title().trim(),
        url: this.newFeed.url().trim(),
        status: this.newFeed.status() || 'approved',
        user_id: this.normalizedUserId(this.newFeed),
      })
      .then(() => {
        this.resetNewFeed();
      })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.admin.page.save_error'));
      })
      .finally(() => {
        this.savingNewFeed = false;
        m.redraw();
      });
  }

  updateFeed(feed, attribute, value) {
    feed
      .save({ [attribute]: value })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.admin.page.save_error'));
      });
  }

  feedUser(feed) {
    const user = feed.user && feed.user();

    if (user) return user;

    const userId = feed.userId && feed.userId();

    return userId ? app.store.getById('users', String(userId)) : null;
  }

  normalizedUserId(feed) {
    const userId = feed.userId && feed.userId();

    if (userId === null || userId === undefined || userId === '') return null;

    return Number(userId);
  }

  userBindingControl(feed, isNew = false) {
    const user = this.feedUser(feed);
    const saving = !isNew && this.savingFeedUsers[feed.id()];

    return (
      <div className="RssFeedUserBinding">
        {user ? (
          <>
            <button
              type="button"
              className="RssFeedUserAvatarButton"
              disabled={saving}
              title={app.translator.trans('stezkoy-rss.admin.page.user_change_tooltip')}
              onclick={() => this.openUserSelector(feed, isNew)}
            >
              <Avatar user={user} className="RssFeedUserAvatar" />
            </button>
            <button
              type="button"
              className="RssFeedUserRemoveButton"
              disabled={saving}
              title={app.translator.trans('stezkoy-rss.admin.page.user_unbind_tooltip')}
              onclick={(event) => {
                event.stopPropagation();
                this.setFeedUser(feed, null, isNew);
              }}
            >
              <Icon name="fas fa-times" />
            </button>
          </>
        ) : (
          <button
            type="button"
            className="RssFeedUserAddButton"
            disabled={saving}
            title={app.translator.trans('stezkoy-rss.admin.page.user_bind_tooltip')}
            onclick={() => this.openUserSelector(feed, isNew)}
          >
            <Icon name={saving ? 'fas fa-spinner fa-spin' : 'fas fa-plus'} />
          </button>
        )}
      </div>
    );
  }

  openUserSelector(feed, isNew = false) {
    const user = this.feedUser(feed);

    app.modal.show(UserSelectionModal, {
      title: app.translator.trans('stezkoy-rss.admin.page.user_select_title'),
      selected: user ? [user] : [],
      maxItems: 1,
      onsubmit: (users) => this.setFeedUser(feed, users[0] || null, isNew),
    });
  }

  setFeedUser(feed, user, isNew = false) {
    const userId = user ? Number(user.id()) : null;

    feed.pushAttributes({ user_id: userId });
    feed.pushData({
      relationships: {
        user: user || {
          data: null,
        },
      },
    });

    if (isNew) return;

    this.savingFeedUsers[feed.id()] = true;

    feed
      .save({ user_id: userId })
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.admin.page.save_error'));
      })
      .finally(() => {
        delete this.savingFeedUsers[feed.id()];
        m.redraw();
      });
  }

  deleteFeed(feed) {
    if (!confirm(app.translator.trans('stezkoy-rss.admin.page.delete_confirmation'))) return;

    feed
      .delete()
      .then(() => m.redraw())
      .catch(() => {
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.admin.page.delete_error'));
      });
  }
}
