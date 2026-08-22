import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import app from 'flarum/forum/app';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import classList from 'flarum/common/utils/classList';
import RssDiscussionListItem, { rssItemDate } from './RssDiscussionListItem';
import { rssIndexEnabled } from '../rssIndexState';

function hasActiveFilter(filter) {
  return Object.keys(filter || {}).some((key) => {
    const value = filter[key];

    return value !== undefined && value !== null && value !== '';
  });
}

export function isPlainDiscussionIndex(state) {
  const params = state.getParams();

  return !params.q && !params.tags && !hasActiveFilter(params.filter) && (!params.sort || params.sort === 'latest');
}

export function shouldMixRssIntoDiscussionList(state) {
  return rssIndexEnabled() && isPlainDiscussionIndex(state);
}

export default class MixedDiscussionList extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.visibleTarget = this.pageSize();
    this.rssPages = [];
    this.rssPage = 0;
    this.rssHasNext = true;
    this.rssStarted = false;
    this.rssInitialLoading = false;
    this.rssLoadingNext = false;
    this.rssErrorShown = false;
    this.fillingWindow = false;
    this.fillPromise = null;

    if (this.shouldMix()) {
      this.loadRssPage(1);
    }
  }

  shouldMix() {
    return shouldMixRssIntoDiscussionList(this.attrs.state);
  }

  pageSize() {
    return this.attrs.state.pageSize || 20;
  }

  setVisibleTarget() {
    this.visibleTarget = Math.max(this.visibleTarget || 0, this.pageSize());
  }

  loadRssPage(page) {
    if ((page > 1 && !this.rssHasNext) || this.rssInitialLoading || this.rssLoadingNext) {
      return Promise.resolve();
    }

    const limit = this.pageSize();
    const offset = (page - 1) * limit;
    const query = new URLSearchParams({
      'page[offset]': String(offset),
      'page[limit]': String(limit),
    });

    this.rssStarted = true;

    if (page === 1) {
      this.rssInitialLoading = true;
    } else {
      this.rssLoadingNext = true;
    }

    return app
      .request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + `/rss-items?${query.toString()}`,
      })
      .then((response) => {
        const items = response.data || [];

        if (page === 1) {
          this.rssPages = [];
        }

        this.rssPages.push({ number: page, items });
        this.rssPage = page;
        this.rssHasNext = items.length === limit;
      })
      .catch(() => {
        this.rssHasNext = false;

        if (!this.rssErrorShown) {
          this.rssErrorShown = true;
          app.alerts.show({ type: 'error' }, app.translator.trans('stezkoy-rss.forum.load_error'));
        }
      })
      .finally(() => {
        this.rssInitialLoading = false;
        this.rssLoadingNext = false;
        m.redraw();
      });
  }

  loadMore() {
    this.visibleTarget += this.pageSize();

    return this.ensureStableWindow();
  }

  sourceBoundary(source, entries) {
    if (source === 'discussion') {
      if (!this.attrs.state.hasNext()) return null;

      const discussions = entries.filter((entry) => entry.type === 'discussion');

      return discussions.length ? Math.min(...discussions.map((entry) => entry.time)) : Infinity;
    }

    if (!this.rssHasNext) return null;

    const rssItems = entries.filter((entry) => entry.type === 'rss');

    return rssItems.length ? Math.min(...rssItems.map((entry) => entry.time)) : Infinity;
  }

  blockingSource(entries) {
    const discussionBoundary = this.sourceBoundary('discussion', entries);
    const rssBoundary = this.sourceBoundary('rss', entries);

    if (discussionBoundary === null && rssBoundary === null) return null;
    if (discussionBoundary === null) return 'rss';
    if (rssBoundary === null) return 'discussion';

    return discussionBoundary >= rssBoundary ? 'discussion' : 'rss';
  }

  pendingBoundary(entries) {
    const boundaries = [this.sourceBoundary('discussion', entries), this.sourceBoundary('rss', entries)].filter((boundary) => boundary !== null);

    return boundaries.length ? Math.max(...boundaries) : null;
  }

  stableEntries(entries = this.mixedEntries()) {
    const boundary = this.pendingBoundary(entries);

    if (boundary === null) return entries;

    return entries.filter((entry) => entry.time >= boundary);
  }

  canLoadSource(source) {
    const state = this.attrs.state;

    if (source === 'discussion') {
      return state.hasNext() && !state.isInitialLoading() && !state.isLoadingNext();
    }

    return this.rssHasNext && !this.rssInitialLoading && !this.rssLoadingNext;
  }

  loadSource(source) {
    if (!this.canLoadSource(source)) return Promise.resolve();

    return source === 'discussion' ? this.attrs.state.loadNext() : this.loadRssPage(this.rssPage + 1);
  }

  needsMoreStableEntries(entries = this.mixedEntries()) {
    if (this.stableEntries(entries).length >= this.visibleTarget) return false;

    const source = this.blockingSource(entries);

    return !!source && this.canLoadSource(source);
  }

  ensureStableWindow() {
    if (this.fillPromise) return this.fillPromise;

    this.fillingWindow = true;

    this.fillPromise = this.fillStableWindow()
      .finally(() => {
        this.fillingWindow = false;
        this.fillPromise = null;
        m.redraw();
      });

    return this.fillPromise;
  }

  async fillStableWindow() {
    let guard = 0;

    while (this.shouldMix() && guard < 20) {
      guard++;

      const entries = this.mixedEntries();

      if (this.stableEntries(entries).length >= this.visibleTarget) break;

      const source = this.blockingSource(entries);

      if (!source || !this.canLoadSource(source)) break;

      await this.loadSource(source);
    }
  }

  discussionEntries() {
    const state = this.attrs.state;

    return state
      .getPages()
      .flatMap((page) => Array.from(page.items || []))
      .map((discussion) => {
        const date = discussion.lastPostedAt() || discussion.createdAt() || new Date(0);

        return {
          type: 'discussion',
          key: `discussion-${discussion.id()}`,
          time: date.getTime(),
          discussion,
        };
      });
  }

  rssEntries() {
    return this.rssPages
      .flatMap((page) => page.items)
      .filter((item) => !item.attributes?.discussion_id)
      .map((item) => ({
        type: 'rss',
        key: `rss-${item.id}`,
        time: rssItemDate(item).getTime(),
        item,
      }));
  }

  mixedEntries() {
    const discussionEntries = this.discussionEntries();

    return discussionEntries.concat(this.rssEntries()).sort((a, b) => b.time - a.time);
  }

  view() {
    const state = this.attrs.state;

    if (!this.shouldMix()) {
      return <DiscussionList state={state} />;
    }

    this.setVisibleTarget();

    if (!this.rssStarted) {
      this.loadRssPage(1);
    }

    if (!state.isInitialLoading() && !this.rssInitialLoading && this.needsMoreStableEntries()) {
      this.ensureStableWindow();
    }

    const stableEntries = this.stableEntries();
    const entries = stableEntries.slice(0, this.visibleTarget);
    const isInitialLoading = state.isInitialLoading() || (this.rssInitialLoading && !this.rssPages.length) || (this.fillingWindow && !entries.length);
    const isLoadingMore = state.isLoadingNext() || this.rssLoadingNext || this.fillingWindow;
    const hasMore = stableEntries.length > entries.length || !!this.blockingSource(this.mixedEntries());

    if (!entries.length && isInitialLoading) {
      return (
        <div className="DiscussionList RssMixedDiscussionList">
          <LoadingIndicator />
        </div>
      );
    }

    if (!entries.length) {
      return (
        <div className="DiscussionList RssMixedDiscussionList">
          <Placeholder text={app.translator.trans('core.forum.discussion_list.empty_text')} />
        </div>
      );
    }

    return (
      <div className={classList('DiscussionList', 'RssMixedDiscussionList')}>
        <ul role="feed" aria-busy={isInitialLoading || isLoadingMore} className="DiscussionList-discussions">
          {entries.map((entry, index) => (
            <li key={entry.key} data-id={entry.type === 'discussion' ? entry.discussion.id() : undefined} role="article" aria-setsize="-1" aria-posinset={index + 1}>
              {entry.type === 'discussion' ? (
                <DiscussionListItem discussion={entry.discussion} params={state.getParams()} />
              ) : (
                <RssDiscussionListItem item={entry.item} />
              )}
            </li>
          ))}
        </ul>
        <div className="DiscussionList-loadMore">
          {isLoadingMore ? (
            <LoadingIndicator />
          ) : (
            hasMore && (
              <Button className="Button" onclick={() => this.loadMore()}>
                {app.translator.trans('core.forum.discussion_list.load_more_button')}
              </Button>
            )
          )}
        </div>
      </div>
    );
  }
}
