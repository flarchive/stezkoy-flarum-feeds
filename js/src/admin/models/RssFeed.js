import Model from 'flarum/common/Model';

export default class RssFeed extends Model {
  title() {
    return Model.attribute('title').call(this);
  }

  url() {
    return Model.attribute('url').call(this);
  }

  status() {
    return Model.attribute('status').call(this);
  }

  userId() {
    return Model.attribute('user_id').call(this);
  }

  user() {
    return Model.hasOne('user').call(this);
  }

  createdAt() {
    return Model.attribute('created_at', Model.transformDate).call(this);
  }
}
