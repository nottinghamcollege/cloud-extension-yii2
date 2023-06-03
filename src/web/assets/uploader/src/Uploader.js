/** global: Craft */
/** global: Garnish */

const getUploadUrl = function (data) {
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
};

Craft.CloudUploader = Craft.Uploader.extend({
  init: function ($element, settings) {
    this.base($element, settings);
    this.uploader.off('fileuploadadd');
    this.uploader = null;
    this.formData = {};
    this.$fileInput = this.$element.prev();
    this.$fileInput.on('change', this.upload.bind(this));
    this._inProgressCounter = 0;
  },

  /**
   * Set uploader parameters.
   */
  setParams: function (paramObject) {
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
  upload: function (event) {
    const files = event.target.files;

    for(const file of files) {
      this._validFileCounter++;
    }

    for(const file of files) {
      this.uploadFile(file);

      if (++this._totalFileCounter === files.length) {
        this._totalFileCounter = 0;
        this._validFileCounter = 0;
        this.processErrorMessages();
      }
    };
  },
  uploadFile: async function (file) {
    Object.assign(this.formData, {
      filename: file.name,
    });

    try {
      this._inProgressCounter++;
      this.$element.trigger('fileuploadstart');
      this.$element.trigger('fileuploadprogressall', [{
        loaded: this._inProgressCounter,
        total: this._validFileCounter,
      }]);
      let response = await Craft.sendActionRequest(
        'POST',
        'cloud/get-upload-url',
        {
          data: this.formData,
        }
      );

      Object.assign(this.formData, response.data);

      await axios.put(response.data.url, file, {
        headers: {
          'Content-Type': file.type,
        },
        onUploadProgress: (axiosProgressEvent) => {
          this.$element.trigger('fileuploadprogress', [axiosProgressEvent]);
        },
      });

      response = await axios.post(
        Craft.getActionUrl('cloud/create-asset'),
        this.formData
      );
      this.$element.trigger('fileuploaddone', [response.data]);
    } catch (err) {
      this.$element.trigger('fileuploadfail', [{
        message: err.message,
        filename: file.name,
      }]);
    } finally {
      this.$element.trigger('fileuploadalways');
      this._inProgressCounter--;
    }
  },

  /**
   * Get the number of uploads in progress.
   */
  getInProgress: function () {
    return this._inProgressCounter;
  },
});

// Register it!
// Craft.registerAssetUploaderClass('craft\\cloud\\AssetFs', Craft.CloudUploader);
