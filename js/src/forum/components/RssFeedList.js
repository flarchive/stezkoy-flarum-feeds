import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import app from 'flarum/forum/app';
import RssDiscussionListItem from './RssDiscussionListItem';

export default class RssFeedList extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.feeds = [];
    this.page = 0;
    this.moreResults = true;
    this.loading = true;
    this.loadFeeds();
  }

  loadFeeds() {
    if (!this.moreResults || this.loadingNext) return;

    const limit = 20;
    const offset = this.page * limit;
    const query = new URLSearchParams({
      'page[offset]': String(offset),
      'page[limit]': String(limit),
    });

    this.loadingNext = true;

    app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + `/rss-items?${query.toString()}`,
      })
      .then((feeds) => {
        this.feeds = this.feeds.concat(feeds.data);
        this.page++;
        this.moreResults = feeds.data.length === limit;
        this.loading = false;
        this.loadingNext = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        this.loadingNext = false;
        app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.load_error'));
        m.redraw();
      });
  }

  view() {
    if (this.loading) {
      return <LoadingIndicator display="block" />;
    }

    if (!this.feeds.length) {
      return <Placeholder text={app.translator.trans('stezkoy-rss.forum.empty_text')} />;
    }

    return (
      <div className="RssFeedList">
        <div className="DiscussionList">
          <ul className="DiscussionList-discussions">
            {this.feeds.map((feed) => (
              <li key={feed.id}>
                <RssDiscussionListItem item={feed} />
              </li>
            ))}
          </ul>
        </div>
        {this.moreResults && (
          <div className="RssFeedList-loadMore">
            <Button className="Button" loading={this.loadingNext} onclick={() => this.loadFeeds()}>
              {app.translator.trans('stezkoy-rss.forum.load_more')}
            </Button>
          </div>
        )}
      </div>
    );
  }
}
