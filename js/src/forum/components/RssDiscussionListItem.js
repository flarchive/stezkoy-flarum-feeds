import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import Link from 'flarum/common/components/Link';
import humanTime from 'flarum/common/helpers/humanTime';
import app from 'flarum/forum/app';

const COLORS = ['#2f80ed', '#13a085', '#8e44ad', '#c0392b', '#d35400', '#3b7a57', '#6b7eb7', '#b35c7e'];

function hash(value) {
  return Array.from(value || '').reduce((sum, char) => sum + char.codePointAt(0), 0);
}

function itemAttributes(item) {
  return item?.attributes || {};
}

export function rssItemDate(item) {
  const value = itemAttributes(item).published_at;
  const date = value ? new Date(String(value).replace(' ', 'T')) : null;

  return date && !Number.isNaN(date.getTime()) ? date : new Date(0);
}

export function rssFeedColor(item) {
  const siteName = itemAttributes(item).site_name || itemAttributes(item).feed?.title || '';

  return COLORS[hash(siteName) % COLORS.length];
}

export function rssCommentCount(item) {
  return Number(itemAttributes(item).comment_count || 0);
}

export function rssCommentHref(item) {
  const discussionId = itemAttributes(item).discussion_id;

  if (discussionId) {
    return app.route('discussion', { id: discussionId });
  }

  return app.route('rss.item', { id: item.id });
}

export default class RssDiscussionListItem extends Component {
  view() {
    const item = this.attrs.item;
    const attrs = itemAttributes(item);
    const siteName = attrs.site_name || attrs.feed?.title || app.translator.trans('stezkoy-rss.forum.unknown_site');
    const initial = Array.from(String(siteName).trim())[0] || 'R';
    const date = rssItemDate(item);
    const color = rssFeedColor(item);
    const title = attrs.title || attrs.link;
    const commentCount = rssCommentCount(item);

    return (
      <div className="DiscussionListItem RssDiscussionListItem">
        <div className="DiscussionListItem-content Slidable-content read">
          <div className="DiscussionListItem-author">
            <span className="DiscussionListItem-author-avatar RssDiscussionListItem-authorAvatar" title={siteName}>
              <span className="Avatar RssDiscussionListItem-avatar" style={{ backgroundColor: color }}>
                {initial}
              </span>
            </span>
          </div>

          <a href={app.route('rss.item', { id: item.id })} className="DiscussionListItem-main RssDiscussionListItem-main">
            <h2 className="DiscussionListItem-title">{title}</h2>
            <ul className="DiscussionListItem-info">
              <li className="item-tags">
                <span className="TagsLabel">
                  <span className="TagLabel colored text-contrast--light RssDiscussionListItem-tag" style={{ '--tag-bg': color }}>
                    <span className="TagLabel-text">
                      <Icon name="fas fa-rss" className="TagLabel-icon" />
                      <span className="TagLabel-name">{siteName}</span>
                    </span>
                  </span>
                </span>
              </li>
              <li className="item-terminalPost">
                <span>
                  {app.translator.trans('stezkoy-rss.forum.published_at')}{' '}
                  {humanTime(date)}
                </span>
              </li>
            </ul>
          </a>

          <div className="DiscussionListItem-stats RssDiscussionListItem-stats">
            <Link
              href={rssCommentHref(item)}
              className="DiscussionListItem-stats-item RssDiscussionListItem-comments"
              title={app.translator.trans('stezkoy-rss.forum.comment.view_comments')}
            >
              <span className="DiscussionListItem-stats-item-icon">
                <Icon name="far fa-comment" />
              </span>
              <span className="DiscussionListItem-stats-item-label">
                <span aria-hidden="true">{commentCount}</span>
                <span className="visually-hidden">{app.translator.trans('stezkoy-rss.forum.comment.comment_count', { count: commentCount })}</span>
              </span>
            </Link>
          </div>
        </div>
      </div>
    );
  }
}
