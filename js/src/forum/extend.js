import Extend from 'flarum/common/extenders';
import RssFeedPage from './components/RssFeedPage';
import RssItemPage from './components/RssItemPage';

export default [
  new Extend.Routes().add('rss.feed', '/feeds', RssFeedPage),
  new Extend.Routes().add('rss.item', '/feeds/item/:id', RssItemPage),
];
