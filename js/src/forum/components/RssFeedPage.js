import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import WelcomeHero from 'flarum/forum/components/WelcomeHero';
import RssFeedList from './RssFeedList';
import RssToolbar from './RssToolbar';

export default class RssFeedPage extends Page {
  oninit(vnode) {
    super.oninit(vnode);
    app.setTitle(app.translator.trans('stezkoy-rss.forum.page_title'));
    this.bodyClass = 'App--index';
  }

  view() {
    return (
      <PageStructure className="IndexPage RssFeedPage" hero={() => <WelcomeHero />} sidebar={() => <IndexSidebar />}>
        <div className="RssFeedListContent IndexPage-results">
          <RssToolbar />
          <RssFeedList />
        </div>
      </PageStructure>
    );
  }
}
