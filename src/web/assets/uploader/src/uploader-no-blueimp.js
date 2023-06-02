/** global: Craft */
/** global: Garnish */

const getUploadUrl = function(data) {
  const formData = data.formData;
  const file = data.files[0];

  return Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
    data: Object.assign(formData, {
      file: {
        lastModified: file.lastModified,
        name: file.name,
        size: file.size,
        type: file.type,
      },
    }),
  }).then(() => data.submit());
}

Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.off('fileuploadadd');
    this.uploader = null;
    this.formData = {};
    this.$fileInput = this.$element.prev();
    this.$fileInput.on('change', this.upload);
  },

  /**
   * Set uploader parameters.
   */
  setParams: (paramObject) => {
    // If CSRF protection isn't enabled, these won't be defined.
    if (
      typeof Craft.csrfTokenName !== 'undefined' &&
      typeof Craft.csrfTokenValue !== 'undefined'
    ) {
      // Add the CSRF token
      paramObject[Craft.csrfTokenName] = Craft.csrfTokenValue;
    }

    this.formData = paramObject;
  },
  upload: (event) => {
    const files = event.target.files;
    const file = files[0];

    Object.assign(this.formData, {
      filename: file.name
    });
    Craft.sendActionRequest('POST', 'cloud/get-upload-url', {
      data: this.formData
    }).then((response) => {
      Object.assign(this.formData, response.data);

      return axios.put(response.data.url, file, {
        headers: {
          'Content-Type': file.type,
        }
      }).then(() => axios.post(
        Craft.getActionUrl('cloud/create-asset'),
        this.formData,
      ));
    });
  },
});
