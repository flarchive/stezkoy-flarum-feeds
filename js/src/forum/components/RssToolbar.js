import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';
import RssSubmitModal from './RssSubmitModal';

export default class RssToolbar extends Component {
  view() {
    return (
      <div className="IndexPage-toolbar rss-subsec">
        <ul className="IndexPage-toolbar-action">
          <li className="item-markAllAsRead">
            <Button
              className="Button"
              icon="fas fa-plus"
              onclick={() => app.modal.show(RssSubmitModal)}
            >
              {app.translator.trans('stezkoy-rss.forum.submit_feed_button')}
            </Button>
          </li>
        </ul>
      </div>
    );
  }
}
