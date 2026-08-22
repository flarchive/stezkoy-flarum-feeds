import app from 'flarum/forum/app';
import DOMPurify from 'dompurify';
import Page from 'flarum/common/components/Page';
import Button from 'flarum/common/components/Button';
import Avatar from 'flarum/common/components/Avatar';
import Icon from 'flarum/common/components/Icon';
import Placeholder from 'flarum/common/components/Placeholder';
import LogInModal from 'flarum/forum/components/LogInModal';
import PageStructure from 'flarum/forum/components/PageStructure';
import humanTime from 'flarum/common/helpers/humanTime';
import username from 'flarum/common/helpers/username';
import { rssFeedColor } from './RssDiscussionListItem';

export default class RssItemPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.item = null;
    this.loading = true;
    this.submitting = false;
    this.composing = false;
    this.comment = '';
    this.bodyClass = 'App--discussion';

    this.loadItem();
  }

  loadItem() {
    const id = m.route.param('id');

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + `/rss-items/${id}`,
      })
      .then((response) => {
        this.item = response.data;
        this.loading = false;

        const discussionId = this.attrsForItem().discussion_id;

        if (discussionId) {
          m.route.set(app.route('discussion', { id: discussionId }), {}, { replace: true });
          return;
        }

        app.setTitle(this.attrsForItem().title || app.translator.trans('stezkoy-rss.forum.page_title'));
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.load_error'));
        m.redraw();
      });
  }

  attrsForItem() {
    return this.item?.attributes || {};
  }

  submitComment() {
    const content = this.comment.trim();

    if (!content || this.submitting) return;

    this.submitting = true;

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + `/rss-items/${this.item.id}/comments`,
        body: {
          data: {
            type: 'rss-item-comments',
            attributes: {
              content,
            },
          },
        },
      })
      .then((response) => {
        const attrs = response.data?.attributes || {};
        const discussionId = attrs.discussion_id;
        const postNumber = attrs.post_number;

        app.discussions.clear();
        m.route.set(app.route(postNumber && postNumber !== 1 ? 'discussion.near' : 'discussion', { id: discussionId, near: postNumber }));
      })
      .catch(() => {
        this.submitting = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.comment.submit_error'));
        m.redraw();
      });
  }

  startReply() {
    if (!app.session.user) {
      app.modal.show(LogInModal);
      return;
    }

    this.composing = true;
    m.redraw();

    setTimeout(() => {
      this.element?.querySelector('.RssItemReplyComposer textarea')?.focus();
    });
  }

  cancelReply() {
    this.composing = false;
    this.comment = '';
  }

  view() {
    return (
      <PageStructure className="DiscussionPage RssItemPage" loading={this.loading} hero={() => this.heroView()} sidebar={() => this.sidebarView()}>
        {this.item ? <div className="DiscussionPage-stream">{this.itemView()}</div> : <Placeholder text={app.translator.trans('stezkoy-rss.forum.empty_text')} />}
      </PageStructure>
    );
  }

  heroView() {
    if (!this.item) return null;

    const attrs = this.attrsForItem();
    const siteName = attrs.site_name || attrs.feed?.title || app.translator.trans('stezkoy-rss.forum.unknown_site');

    return (
      <header className="Hero DiscussionHero RssItemHero">
        <div className="container">
          <ul className="DiscussionHero-items">
            <li className="item-rssSite">
              <span className="RssItemHero-site">
                <Icon name="fas fa-rss" />
                <span>{siteName}</span>
              </span>
            </li>
            <li className="item-title">
              <h1 className="DiscussionHero-title">{attrs.title}</h1>
            </li>
          </ul>
        </div>
      </header>
    );
  }

  sidebarView() {
    if (!this.item) return null;

    return (
      <nav className="DiscussionPage-nav">
        <ul>
          <li className="item-controls">
            <Button className="Button Button--primary App-primaryControl RssItemReplyButton" icon="fas fa-reply" onclick={() => this.startReply()}>
              {app.translator.trans(app.session.user ? 'core.forum.discussion_controls.reply_button' : 'core.forum.discussion_controls.log_in_to_reply_button')}
            </Button>
          </li>
        </ul>
      </nav>
    );
  }

  itemView() {
    return (
      <div className="PostStream" role="feed" aria-live="off" aria-busy={this.submitting ? 'true' : 'false'}>
        <div className="PostStream-item" data-index="0" data-number="1">
          {this.articlePostView()}
        </div>
        <div className="PostStream-item" data-index="1">
          {this.replyView()}
        </div>
      </div>
    );
  }

  articlePostView() {
    const attrs = this.attrsForItem();
    const date = attrs.published_at ? new Date(String(attrs.published_at).replace(' ', 'T')) : null;
    const siteName = attrs.site_name || attrs.feed?.title || app.translator.trans('stezkoy-rss.forum.unknown_site');
    const initial = Array.from(String(siteName).trim())[0] || 'R';
    const color = rssFeedColor(this.item);

    return (
      <article className="Post CommentPost RssItemPost">
        <div className="Post-container">
          <div className="Post-side">
            <span className="Avatar Post-avatar RssItemPost-avatar" style={{ backgroundColor: color }}>
              {initial}
            </span>
          </div>
          <div className="Post-main">
            <header className="Post-header">
              <ul>
                <li className="item-user">
                  <div className="PostUser">
                    <h3 className="PostUser-name">{siteName}</h3>
                  </div>
                </li>
                {date && (
                  <li className="item-meta">
                    <div className="Dropdown PostMeta">
                      <button type="button" className="Button Button--text">
                        {humanTime(date)}
                      </button>
                    </div>
                  </li>
                )}
              </ul>
            </header>
            <div className="Post-body">{this.articleBody(attrs)}</div>
          </div>
        </div>
      </article>
    );
  }

  articleBody(attrs) {
    const sourceLink = attrs.link ? (
      <p className="RssItemPost-source">
        <a href={attrs.link} target="_blank" rel="noopener noreferrer">
          {app.translator.trans('stezkoy-rss.forum.original_article_link')}
        </a>
      </p>
    ) : null;

    if (attrs.contentHtml) {
      const clean = DOMPurify.sanitize(attrs.contentHtml, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'span', 'div'],
        ALLOWED_ATTR: ['href', 'src', 'alt', 'title'],
      });
      return (
        <>
          {sourceLink}
          <div
            className="RssItemPost-content"
            oncreate={(vnode) => { vnode.dom.innerHTML = clean; }}
            onupdate={(vnode) => { vnode.dom.innerHTML = clean; }}
          />
        </>
      );
    }

    return sourceLink;
  }

  replyView() {
    return this.composing ? this.replyComposerView() : this.replyPlaceholderView();
  }

  replyPlaceholderView() {
    return (
      <button type="button" className="Post ReplyPlaceholder" onclick={() => this.startReply()}>
        <div className="Post-container">
          <div className="Post-side">
            <Avatar user={app.session.user} className="Post-avatar" />
          </div>
          <div className="Post-main">
            <span className="Post-header">{app.translator.trans('core.forum.post_stream.reply_placeholder')}</span>
          </div>
        </div>
      </button>
    );
  }

  replyComposerView() {
    return (
      <article className="Post CommentPost RssItemReplyComposer" aria-busy={this.submitting ? 'true' : 'false'}>
        <div className="Post-container">
          <div className="Post-side">
            <Avatar user={app.session.user} className="Post-avatar" />
          </div>
          <div className="Post-main">
            <header className="Post-header">
              <ul>
                <li className="item-user">
                  <div className="PostUser">
                    <h3 className="PostUser-name">{username(app.session.user)}</h3>
                  </div>
                </li>
              </ul>
            </header>
            <div className="Post-body">
              <textarea
                className="FormControl"
                rows="5"
                value={this.comment}
                placeholder={app.translator.trans('stezkoy-rss.forum.comment.placeholder')}
                disabled={this.submitting}
                oninput={(event) => {
                  this.comment = event.target.value;
                }}
              />
            </div>
            <footer className="Post-footer">
              <ul>
                <li className="item-submit">
                  <Button className="Button Button--primary" loading={this.submitting} disabled={!this.comment.trim()} onclick={() => this.submitComment()}>
                    {app.translator.trans('stezkoy-rss.forum.comment.submit_button')}
                  </Button>
                </li>
                <li className="item-cancel">
                  <Button className="Button Button--link" disabled={this.submitting} onclick={() => this.cancelReply()}>
                    {app.translator.trans('stezkoy-rss.forum.comment.cancel_button')}
                  </Button>
                </li>
              </ul>
            </footer>
          </div>
        </div>
      </article>
    );
  }
}
